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
        $users = [];
        // Use high value for MAX because pagination is broken on Webex side.
        $nextPageUrl = 'https://webexapis.com/v1/people?max=1000';

        do {
            $peopleResponse = $this->client->request('GET', $nextPageUrl, [
                'auth_bearer' => $token,
                'headers' => [
                    'accept' => 'application/json',
                ],
            ]);

            if (403 === $peopleResponse->getStatusCode()) {
                throw new UserExtractionFailedException('webex', 'Only Webex Site Administrator can use this application.');
            }

            $people = $peopleResponse->toArray();
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

            // See if there is a next page:
            $nextPageUrl = null;
            $headers = $peopleResponse->getHeaders();
            // Looking for a header like this:
            // Link: <https://webexapis.com/v1/people?displayName=Harold&max=10&before&after=Y2lzY29zcGFyazovL3VzL1BFT1BMRS83MTZlOWQxYy1jYTQ0LTRmZWQtOGZjYS05ZGY0YjRmNDE3ZjU>; rel="next"
            if (isset($headers['link']) && preg_match('/<(.+)>; rel="next"/', $headers['link'][0], $matches)) {
                $nextPageUrl = $matches[1];
            }
        } while ($nextPageUrl);

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
