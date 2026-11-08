<?php

/*
 * This file is part of the Secret Santa project.
 *
 * (c) JoliCode <coucou@jolicode.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace JoliCode\SecretSanta\Tests\Fake;

use Symfony\Contracts\HttpClient\ResponseInterface;

/**
 * Slack Web API: paginated users.list, usergroups.list and chat.postMessage, plus the Slack Connect
 * methods: users.conversations with shared channels, conversations.members, users.info and team.info
 * for an external user, and conversations.open to message them.
 */
final class FakeSlackApi extends FakeChatApi
{
    public function postedMessages(): array
    {
        $messages = [];
        foreach ($this->requests as $request) {
            if ('/api/chat.postMessage' !== $request['path']) {
                continue;
            }
            parse_str($request['body'], $body);
            // The text is the notification fallback, the blocks hold the full message
            $messages[] = ['to' => $body['channel'], 'text' => $body['text'] . "\n" . $body['blocks']];
        }

        return $messages;
    }

    protected function answer(string $method, string $path, array $query, string $body, array $options): ResponseInterface
    {
        // Slack read methods are GET with query parameters, write methods are POST with a form body
        parse_str($body, $form);
        $params = $query + $form;

        return $this->json(match ($path) {
            '/api/usergroups.list' => [
                'ok' => true,
                'usergroups' => [
                    ['id' => 'S1', 'name' => 'Devs', 'users' => ['U2', 'U3']],
                ],
            ],
            '/api/users.list' => $this->usersList((string) ($params['cursor'] ?? '')),
            '/api/users.conversations' => $this->usersConversations($params['user'] ?? null),
            '/api/conversations.members' => $this->conversationsMembers($params['channel'], (string) ($params['cursor'] ?? '')),
            '/api/users.info' => 'UEXT' === $params['user']
                ? ['ok' => true, 'user' => $this->member('UEXT', 'frank', 'Frank', ['team_id' => 'TEXT', 'is_stranger' => true])]
                : ['ok' => false, 'error' => 'user_not_found'],
            '/api/team.info' => ['ok' => true, 'team' => ['id' => 'TEXT', 'name' => 'Other Workspace']],
            '/api/conversations.open' => ['ok' => true, 'channel' => ['id' => 'D' . $params['users']]],
            '/api/chat.postMessage' => \in_array($params['channel'], $this->failingUsers, true)
                ? ['ok' => false, 'error' => 'channel_not_found']
                : ['ok' => true, 'channel' => $params['channel'], 'ts' => '1700000000.000001'],
            default => ['ok' => false, 'error' => 'unknown_method'],
        });
    }

    /**
     * The members of each channel: #santa spans two pages, the first one has a next cursor.
     *
     * @return array<string, mixed>
     */
    private function conversationsMembers(string $channel, string $cursor): array
    {
        $pages = [
            'C_LOCAL' => ['' => ['members' => ['U1', 'U3'], 'next_cursor' => '']],
            'C_SANTA' => [
                '' => ['members' => ['U1', 'U2'], 'next_cursor' => 'page2'],
                'page2' => ['members' => ['UEXT'], 'next_cursor' => ''],
            ],
            'C_OTHER' => ['' => ['members' => ['U2', 'UEXT'], 'next_cursor' => '']],
        ];

        return [
            'ok' => true,
            'members' => $pages[$channel][$cursor]['members'],
            'response_metadata' => ['next_cursor' => $pages[$channel][$cursor]['next_cursor']],
        ];
    }

    /**
     * Two pages: the first one has a next cursor, the second one ends the pagination.
     *
     * @return array<string, mixed>
     */
    private function usersList(string $cursor): array
    {
        $pages = [
            '' => [
                'members' => [
                    $this->member('U1', 'alice', 'Alice'),
                    $this->member('U2', 'bob', 'Bob'),
                    $this->member('UBOT', 'santabot', 'Santa Bot', ['is_bot' => true]),
                ],
                'next_cursor' => 'page2',
            ],
            'page2' => [
                'members' => [
                    $this->member('U3', 'carol', 'Carol'),
                    $this->member('U4', 'dave', 'Dave'),
                    $this->member('U5', 'eve', 'Eve', ['is_restricted' => true]),
                    $this->member('UGHOST', 'ghost', 'Ghost', ['deleted' => true]),
                ],
                'next_cursor' => '',
            ],
        ];

        return [
            'ok' => true,
            'members' => $pages[$cursor]['members'],
            'response_metadata' => ['next_cursor' => $pages[$cursor]['next_cursor']],
        ];
    }

    /**
     * A channel shared with another workspace (Slack Connect) the bot is a member of, unless overridden.
     *
     * @return array<string, mixed>
     */
    /**
     * The channels of the admin U1, or the bot's own ones without a user: the bot is not in #other.
     *
     * @return array<string, mixed>
     */
    private function usersConversations(?string $user): array
    {
        $channels = [
            $this->channel('C_LOCAL', 'local', ['is_ext_shared' => false]),
            $this->channel('C_SANTA', 'santa'),
        ];

        if ('U1' === $user) {
            $channels[] = $this->channel('C_OTHER', 'other');
        }

        return [
            'ok' => true,
            'channels' => $channels,
            'response_metadata' => ['next_cursor' => ''],
        ];
    }

    /**
     * @param array<string, mixed> $overrides
     *
     * @return array<string, mixed>
     */
    private function channel(string $id, string $name, array $overrides = []): array
    {
        return $overrides + [
            'id' => $id,
            'name' => $name,
            'is_ext_shared' => true,
            'is_pending_ext_shared' => false,
        ];
    }

    /**
     * @param array<string, mixed> $overrides
     *
     * @return array<string, mixed>
     */
    private function member(string $id, string $name, string $realName, array $overrides = []): array
    {
        return $overrides + [
            'id' => $id,
            'name' => $name,
            'deleted' => false,
            'is_bot' => false,
            'is_restricted' => false,
            'profile' => [
                'real_name' => $realName,
                // Served by the app itself: an external host would slow down the E2E tests on DNS failures
                'image_192' => \sprintf('https://localhost/images/logo.png?user=%s', $name),
            ],
        ];
    }
}
