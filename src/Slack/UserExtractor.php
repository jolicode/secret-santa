<?php

/*
 * This file is part of the Secret Santa project.
 *
 * (c) JoliCode <coucou@jolicode.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace JoliCode\SecretSanta\Slack;

use CL\Slack\Model\User as SlackUser;
use CL\Slack\Payload\UsersListPayload;
use CL\Slack\Payload\UsersListPayloadResponse;
use JoliCode\SecretSanta\Exception\UserExtractionFailedException;
use JoliCode\SecretSanta\User;

class UserExtractor
{
    private $apiHelper;

    public function __construct(ApiHelper $apiHelper)
    {
        $this->apiHelper = $apiHelper;
    }

    public function extractAll(string $token): array
    {
        $payload = new UsersListPayload();

        try {
            /** @var $response UsersListPayloadResponse */
            $response = $this->apiHelper->sendPayload($payload, $token);
        } catch (\Throwable $t) {
            throw new UserExtractionFailedException('Could not fetch members in team', 0, $t);
        }

        /** @var SlackUser[] $slackUsers */
        $slackUsers = array_filter($response->getUsers(), function (SlackUser $user) {
            return
                !$user->isBot()
                && !$user->isDeleted()
                && 'slackbot' !== $user->getName()
            ;
        });

        $users = [];

        foreach ($slackUsers as $slackUser) {
            $user = new User(
                $slackUser->getId(),
                $slackUser->getProfile()->getRealName(),
                [
                    'nickname' => $slackUser->getName(),
                    'image' => $slackUser->getProfile()->getImage32(),
                    'restricted' => $slackUser->isRestricted(),
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
