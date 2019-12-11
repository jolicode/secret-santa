<?php

/*
 * This file is part of the Secret Santa project.
 *
 * (c) JoliCode <coucou@jolicode.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace JoliCode\SecretSanta\Zoom;

use JoliCode\SecretSanta\Application\ZoomApplication;
use JoliCode\SecretSanta\Exception\UserExtractionFailedException;
use JoliCode\SecretSanta\Model\User;
use Symfony\Contracts\HttpClient\HttpClientInterface;

class UserExtractor
{
    private $httpClient;

    public function __construct(HttpClientInterface $httpClient)
    {
        $this->httpClient = $httpClient;
    }

    /**
     * @return User[]
     */
    public function extractAll(string $token): array
    {
        /** @var array $zoomUsers */
        $zoomUsers = [];
        $cursor = '';
        $startTime = time();

        do {
            if ((time() - $startTime) > 19) {
                throw new UserExtractionFailedException(ZoomApplication::APPLICATION_CODE, 'Took too much time to retrieve all the users on your team.');
            }

            try {
                $contacts = $this->httpClient->request('GET', 'https://api.zoom.us/v2/contacts', [
                    'query' => [
                        'page_size' => 25,
                    ],
                    'auth_bearer' => $token,
                ]);

                $contacts = $contacts->toArray();
            } catch (\Throwable $t) {
                throw new UserExtractionFailedException(ZoomApplication::APPLICATION_CODE, 'Could not fetch members in team.', $t);
            }

            $zoomUsers = array_merge($zoomUsers, $contacts['contacts']);
            $cursor = $contacts['next_page_token'];
        } while (!empty($cursor));

        $users = [];

        foreach ($zoomUsers as $zoomUser) {
            $user = new User(
                $zoomUser['id'],
                $zoomUser['first_name'] . ' ' . $zoomUser['last_name'],
                [
                    // @todo would be nice to have them...
                    //'image' => $zoomUser->getProfile()->getImage192(),
                    'restricted' => false,
                ]
            );

            $users[$user->getIdentifier()] = $user;
        }

        uasort($users, function (User $a, User $b) {
            return strnatcasecmp($a->getName(), $b->getName());
        });

        return $users;
    }
}
