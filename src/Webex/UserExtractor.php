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

use JoliCode\SecretSanta\Exception\UserExtractionFailedException;
use JoliCode\SecretSanta\Model\User;
use Symfony\Contracts\HttpClient\HttpClientInterface;

class UserExtractor
{
    public function __construct(readonly private HttpClientInterface $client)
    {
    }

    /**
     * @return array<User>
     */
    public function extractAll(string $token): array
    {
        $people = $this->client->request('GET', 'https://webexapis.com/v1/people', [
            'auth_bearer' => $token,
            'headers' => [
                'accept' => 'application/json',
            ],
        ]);

        if (403 === $people->getStatusCode()) {
            throw new UserExtractionFailedException('webex', 'Only Webex Site Administrator can use this application.');
        }

        $users = [];
        $people = $people->toArray();
        foreach ($people['items'] as $person) {
            $users[$person['id']] = new User(
                $person['id'],
                $person['displayName'],
                [
                    'nickname' => $person['nickName'] ?: null,
                    'image' => $person['avatar'] ?? null, // Huge 1600px image!
                ]
            );
        }

        return $users;
    }

    public function getMe(string $token): User
    {
        $me = $this->client->request('GET', 'https://webexapis.com/v1/people/me', [
            'auth_bearer' => $token,
            'headers' => [
                'accept' => 'application/json',
            ],
        ]);

        if (403 === $me->getStatusCode()) {
            throw new UserExtractionFailedException('webex', 'Only Webex Site Administrator can use this application.');
        }

        $me = $me->toArray();

        return new User($me['id'], $me['displayName']);
    }
}
