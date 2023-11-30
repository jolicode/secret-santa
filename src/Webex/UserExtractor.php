<?php

/*
 * This file is part of the Secret Santa project.
 *
 * (c) JoliCode <coucou@jolicode.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace JoliCode\SecretSanta\Webex;

use JoliCode\SecretSanta\Model\Group;
use JoliCode\SecretSanta\Model\User;
use Symfony\Contracts\HttpClient\HttpClientInterface;

class UserExtractor
{
    public function __construct(readonly private HttpClientInterface $client)
    {
    }

    /**
     * @return array{array<User>, array<Group>}
     */
    public function extractAll(string $token): array
    {
        $users = [];
        $groups = [];

        $roomsResponse = $this->client->request('GET', 'https://webexapis.com/v1/rooms?type=group&sortBy=lastactivity&max=20', [
            'auth_bearer' => $token,
            'headers' => [
                'accept' => 'application/json',
            ],
        ]);

        foreach ($roomsResponse->toArray()['items'] as $room) {
            $roomMembersResponse = $this->client->request('GET', 'https://webexapis.com/v1/memberships?roomId=' . $room['id'], [
                'auth_bearer' => $token,
                'headers' => [
                    'accept' => 'application/json',
                ],
            ]);

            if (404 === $roomMembersResponse->getStatusCode()) {
                // Ignore this room, looks like we cannot read it.
                continue;
            }

            $group = new Group((string) $room['id'], $room['title']);
            $groups[$room['id']] = $group;

            foreach ($roomMembersResponse->toArray()['items'] as $user) {
                $users[$user['personId']] = new User(
                    $user['personId'],
                    $user['personDisplayName']
                );

                $group->addUser($user['personId']);
            }
        }

        return [$users, $groups];
    }

    public function getMe(string $token): User
    {
        $me = $this->client->request('GET', 'https://webexapis.com/v1/people/me', [
            'auth_bearer' => $token,
            'headers' => [
                'accept' => 'application/json',
            ],
        ]);

        $me = $me->toArray();

        return new User($me['id'], $me['displayName']);
    }
}
