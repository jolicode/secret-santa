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

use JoliCode\SecretSanta\Exception\UserExtractionFailedException;
use JoliCode\SecretSanta\Group;
use JoliCode\SecretSanta\User;
use JoliCode\Slack\Api\Client;
use JoliCode\Slack\Api\Model\ObjsUser;
use JoliCode\Slack\ClientFactory;

class UserExtractor
{
    private $apiHelper;

    public function __construct(ApiHelper $apiHelper)
    {
        $this->apiHelper = $apiHelper;
    }

    /**
     * @return User[]
     */
    public function extractAll(string $token): array
    {
        /** @var ObjsUser[] $slackUsers */
        $slackUsers = [];
        $cursor = '';

        $startTime = time();
        do {
            if ((time() - $startTime) > 19) {
                throw new UserExtractionFailedException('Took too much time to retrieve all the users on your team');
            }

            try {
                $response = $this->apiHelper->getClientForToken($token)->usersList([
                    'limit' => 1000,
                    'cursor' => $cursor,
                ]);
            } catch (\Throwable $t) {
                throw new UserExtractionFailedException('Could not fetch members in team', 0, $t);
            }

            $slackUsers = array_merge($slackUsers, $response->getMembers());
            $cursor = $response->getResponseMetadata() ? $response->getResponseMetadata()->getNextCursor() : '';
        } while (!empty($cursor));

        $slackUsers = array_filter($slackUsers, function (ObjsUser $user) {
            return
                !$user->getIsBot()
                && !$user->getDeleted()
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
                    'restricted' => $slackUser->getIsRestricted(),
                ]
            );

            $users[$user->getIdentifier()] = $user;
        }

        uasort($users, function (User $a, User $b) {
            return strnatcasecmp($a->getName(), $b->getName());
        });

        return $users;
    }

    /**
     * @return Group[]
     */
    public function extractGroups(string $token): array
    {
        $groups = [];

        $userGroupsResponse = $this->apiHelper->getClientForToken($token)->usergroupsList([
            'include_users' => true,
        ]);

        // Slack OpenAPI spec does not contains definition yet for usergroups.list response
        // So lets retrieve data from internal data
        foreach ($userGroupsResponse['usergroups'] as $userGroup) {
            $group = new Group(
                $userGroup->id,
                $userGroup->name
            );

            foreach ($userGroup->users as $userId) {
                $group->addUser($userId);
            }

            $groups[$group->getIdentifier()] = $group;
        }

        return $groups;
    }
}
