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

use JoliCode\SecretSanta\Application\SlackApplication;
use JoliCode\SecretSanta\Exception\UserExtractionFailedException;
use JoliCode\SecretSanta\Model\Group;
use JoliCode\SecretSanta\Model\User;
use JoliCode\Slack\Api\Model\ObjsUser;

class UserExtractor
{
    public function __construct(private ClientFactory $clientFactory)
    {
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
            if ((time() - $startTime) > 120) {
                throw new UserExtractionFailedException(SlackApplication::APPLICATION_CODE, 'Took too much time to retrieve all the users on your team.');
            }

            try {
                $response = $this->clientFactory->getClientForToken($token)->usersList([
                    'limit' => 200,
                    'cursor' => $cursor,
                ]);
            } catch (\Throwable $t) {
                throw new UserExtractionFailedException(SlackApplication::APPLICATION_CODE, 'Could not fetch members in team.', $t);
            }

            if (!$response->getOk()) {
                throw new UserExtractionFailedException(SlackApplication::APPLICATION_CODE, 'Could not fetch members in team.');
            }

            $slackUsers = array_merge($slackUsers, $response->getMembers());
            $cursor = $response->getResponseMetadata() ? $response->getResponseMetadata()->getNextCursor() : '';
        } while (!empty($cursor));

        $slackUsers = array_filter($slackUsers, function (ObjsUser $user) {
            return
                !$user->getIsBot()
                && !$user->getDeleted()
                && 'slackbot' !== $user->getName();
        });

        $users = [];

        foreach ($slackUsers as $slackUser) {
            $user = $this->buildUserFromSlack($slackUser);

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

        $userGroupsResponse = $this->clientFactory->getClientForToken($token)->usergroupsList([
            'include_users' => true,
        ]);

        foreach ($userGroupsResponse->getUsergroups() as $userGroup) {
            $group = new Group(
                $userGroup->getId(),
                $userGroup->getName()
            );

            foreach ($userGroup->getUsers() as $userId) {
                $group->addUser($userId);
            }

            $groups[$group->getIdentifier()] = $group;
        }

        return $groups;
    }

    public function getUser(string $token, string $id): User
    {
        $slackUser = $this->clientFactory->getClientForToken($token)->usersInfo([
            'user' => $id,
        ])->getUser();

        return $this->buildUserFromSlack($slackUser);
    }

    private function buildUserFromSlack(ObjsUser $slackUser): User
    {
        return new User(
            $slackUser->getId(),
            $slackUser->getProfile()->getRealName(),
            [
                'nickname' => $slackUser->getName(),
                'image' => $slackUser->getProfile()->getImage192(),
                'restricted' => $slackUser->getIsRestricted(),
            ]
        );
    }
}
