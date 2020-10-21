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
use Symfony\Contracts\HttpClient\Exception\ExceptionInterface;
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
        /** @var array[] $zoomUsers */
        $zoomUsers = [];
        $page = 1;
        $numberOfPages = null;
        $startTime = time();

        do {
            if ((time() - $startTime) > 19) {
                throw new UserExtractionFailedException(ZoomApplication::APPLICATION_CODE, 'Took too much time to retrieve all the users on your team.');
            }

            try {
                $users = $this->httpClient->request('GET', 'https://api.zoom.us/v2/users', [
                    'query' => [
                        'page_size' => 100,
                        'page_number' => $page,
                    ],
                    'auth_bearer' => $token,
                ]);

                $users = $users->toArray();
            } catch (ExceptionInterface $t) {
                throw new UserExtractionFailedException(ZoomApplication::APPLICATION_CODE, 'Could not fetch members in team.', $t);
            }

            $zoomUsers = array_merge($zoomUsers, $users['users']);

            if (null === $numberOfPages) {
                $numberOfPages = ceil($users['total_records'] / $users['page_size']);
            }

            ++$page;
        } while ($numberOfPages >= $page);

        $users = [];

        foreach ($zoomUsers as $zoomUser) {
            $user = new User(
                $zoomUser['id'],
                $zoomUser['first_name'] . ' ' . $zoomUser['last_name'],
                [
                    'image' => isset($zoomUser['pic_url']) ? $zoomUser['pic_url'] : null,
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
