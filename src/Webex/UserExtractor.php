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
            throw new UserExtractionFailedException('Only Webex administrator can use this application.');
        }

        $users = [];
        $people = $people->toArray();
        foreach ($people['items'] as $person) {
            $users[$person['id']] = new User($person['id'], $person['displayName']);
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
        ])->toArray();

        return new User($me['id'], $me['displayName']);
    }
}
