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
use JoliCode\SecretSanta\Model\Config;
use JoliCode\SecretSanta\Model\Group;
use JoliCode\SecretSanta\Model\User;
use JoliCode\Slack\Api\Model\ObjsUser;

class UserExtractor
{
    public function __construct(private ClientFactory $clientFactory)
    {
    }

    /**
     * @return array<User>
     */
    public function loadNextBatchOfUsers(string $token, Config $config): array
    {
        if ($config->areUsersLoaded()) {
            return [];
        }

        $cursor = $config->getUsersPaginationParameters()['cursor'] ?? '';

        try {
            $response = $this->clientFactory->getClientForToken($token)->usersList([
                'limit' => 100,
                'cursor' => $cursor,
            ]);
        } catch (\Throwable $t) {
            throw new UserExtractionFailedException(SlackApplication::APPLICATION_CODE, 'Could not fetch members in team.', $t);
        }

        if (!$response->getOk()) {
            throw new UserExtractionFailedException(SlackApplication::APPLICATION_CODE, 'Could not fetch members in team.');
        }

        $cursor = $response->getResponseMetadata() ? $response->getResponseMetadata()->getNextCursor() : '';

        $config->setUsersPaginationParameters([
            'cursor' => $cursor,
        ]);

        if ('' === $cursor) {
            $config->setUsersLoaded(true);
        }

        $users = [];

        foreach ($response->getMembers() as $slackUser) {
            if ($slackUser->getIsBot() || $slackUser->getDeleted() || 'slackbot' === $slackUser->getName()) {
                continue;
            }

            $user = $this->buildUserFromSlack($slackUser);
            $users[$user->getIdentifier()] = $user;
        }

        if ('' === $cursor) {
            $users += $this->loadExternalUsers($token, $config, $config->getAvailableUsers() + $users);
        }

        return $users;
    }

    /**
     * Channels shared with other workspaces (Slack Connect), as id => name.
     *
     * @return array<string, string>
     */
    public function listSharedChannels(string $token): array
    {
        try {
            // ponytail: no pagination, first 1000 channels only
            $response = $this->clientFactory->getClientForToken($token)->conversationsList([
                'types' => 'public_channel,private_channel',
                'exclude_archived' => true,
                'limit' => 1000,
            ]);
        } catch (\Throwable) {
            return [];
        }

        $channels = [];

        foreach ($response->getChannels() ?? [] as $channel) {
            if ($channel->getIsExtShared()) {
                $channels[$channel->getId()] = $channel->getName();
            }
        }

        return $channels;
    }

    /**
     * @param array<string, string> $sharedChannels Shared channels to expose as groups, as id => name
     *
     * @return array<string, Group>
     */
    public function extractGroups(string $token, array $sharedChannels = []): array
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

        // Shared channels (Slack Connect) chosen by the admin become groups too, so cross-workspace participants can be picked
        foreach ($sharedChannels as $channelId => $channelName) {
            $group = new Group($channelId, '#' . $channelName);

            // ponytail: no pagination, first 1000 members only
            $membersResponse = $this->clientFactory->getClientForToken($token)->conversationsMembers([
                'channel' => $channelId,
                'limit' => 1000,
            ]);

            foreach ($membersResponse->getMembers() ?? [] as $userId) {
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

    /**
     * Users from other workspaces (Slack Connect) are not returned by users.list,
     * but they are listed as members of the shared channels exposed as groups.
     *
     * @param array<string, User> $knownUsers
     *
     * @return array<string, User>
     */
    private function loadExternalUsers(string $token, Config $config, array $knownUsers): array
    {
        $users = [];

        foreach ($config->getGroups() as $group) {
            foreach ($group->getUserIds() as $userId) {
                if (isset($knownUsers[$userId]) || isset($users[$userId])) {
                    continue;
                }

                // ponytail: one users.info per external user, batch via a dedicated cache if it hits rate limits
                $slackUser = $this->clientFactory->getClientForToken($token)->usersInfo(['user' => $userId])->getUser();

                if ($slackUser->getIsBot() || $slackUser->getDeleted()) {
                    continue;
                }

                $user = $this->buildUserFromSlack($slackUser);
                $users[$user->getIdentifier()] = $user;
            }
        }

        return $users;
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
