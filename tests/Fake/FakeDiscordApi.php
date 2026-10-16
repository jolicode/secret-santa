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
 * Discord API: guild members paginated with "after", guild roles, and DM channels to post multipart messages in.
 */
final class FakeDiscordApi extends FakeChatApi
{
    public function postedMessages(): array
    {
        $messages = [];
        foreach ($this->requests as $request) {
            if (!preg_match('{^/api/v10/channels/dm(\d+)/messages$}', $request['path'], $matches)) {
                continue;
            }
            // Multipart body: the message is in the "payload_json" part, the spoil code in the "files[0]" part
            preg_match('{name="payload_json".*?\r\n\r\n(.*?)\r\n--}s', $request['body'], $payload);
            preg_match('{name="files\[0\]".*?\r\n\r\n(.*?)\r\n--}s', $request['body'], $file);
            $messages[] = [
                'to' => $matches[1],
                'text' => json_decode($payload[1], true)['content'],
                'file' => $file[1] ?? null,
            ];
        }

        return $messages;
    }

    protected function answer(string $method, string $path, array $query, string $body, array $options): ResponseInterface
    {
        if (preg_match('{^/api/v10/channels/dm(\d+)/messages$}', $path, $matches)) {
            return \in_array($matches[1], $this->failingUsers, true)
                ? $this->json(['message' => 'Cannot send messages to this user', 'code' => 50007], 403)
                : $this->json(['id' => '900', 'channel_id' => 'dm' . $matches[1]]);
        }

        return match ($path) {
            '/api/v10/users/@me/channels' => $this->json(['id' => 'dm' . json_decode($body, true)['recipient_id']]),
            '/api/v10/guilds/42/roles' => $this->json([
                ['id' => 'R0', 'name' => '@everyone'],
                ['id' => 'R1', 'name' => 'Devs'],
            ]),
            '/api/v10/guilds/42/members' => $this->json($this->members((string) ($query['after'] ?? ''))),
            default => $this->json(['message' => '404: Not Found', 'code' => 0], 404),
        };
    }

    /**
     * Three pages: the last one is empty, which ends the pagination.
     *
     * @return list<array<string, mixed>>
     */
    private function members(string $after): array
    {
        return match ($after) {
            '' => [
                $this->member('101', 'alice'),
                $this->member('102', 'bob', 'Bobby', ['R1']),
                $this->member('999', 'santabot', bot: true),
            ],
            '999' => [
                $this->member('103', 'carol', roles: ['R1']),
                $this->member('104', 'dave'),
                $this->member('105', 'eve'),
            ],
            default => [],
        };
    }

    /**
     * @param list<string> $roles
     *
     * @return array<string, mixed>
     */
    private function member(string $id, string $username, ?string $nick = null, array $roles = [], bool $bot = false): array
    {
        return [
            'user' => ['id' => $id, 'username' => $username, 'avatar' => null, 'bot' => $bot],
            'nick' => $nick,
            'roles' => $roles,
        ];
    }
}
