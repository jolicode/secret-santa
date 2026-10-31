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
use JoliCode\SecretSanta\Model\Channel;
use JoliCode\SecretSanta\Model\Config;
use JoliCode\SecretSanta\Model\Group;
use JoliCode\SecretSanta\Model\User;
use JoliCode\Slack\Api\Model\ObjsConversation;
use JoliCode\Slack\Api\Model\ObjsUser;

class UserExtractor
{
    private const EXTERNAL_USERS_BATCH_SIZE = 25;

    public function __construct(private ClientFactory $clientFactory)
    {
    }

    /**
     * @param array<string, Group> $channels Channels the participants are restricted to, none for the whole workspace
     *
     * @return array<User>
     */
    public function loadNextBatchOfUsers(string $token, Config $config, array $channels): array
    {
        if ($config->areUsersLoaded()) {
            return [];
        }

        if (isset($config->getUsersPaginationParameters()['external_ids'])) {
            return $this->loadNextBatchOfExternalUsers($token, $config);
        }

        $cursor = $config->getUsersPaginationParameters()['cursor'] ?? '';
        // Channel members left out of the participants (bots, deleted accounts) are remembered across the pages,
        // not to mistake them for external members afterwards
        $skippedIds = $config->getUsersPaginationParameters()['skipped_ids'] ?? [];

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
        $memberIds = $this->listMemberIds($channels);
        $users = [];

        foreach ($response->getMembers() as $slackUser) {
            if ($slackUser->getIsBot() || $slackUser->getDeleted() || 'slackbot' === $slackUser->getName()) {
                if (isset($memberIds[$slackUser->getId()])) {
                    $skippedIds[$slackUser->getId()] = true;
                }

                continue;
            }

            if ([] !== $channels && !isset($memberIds[$slackUser->getId()])) {
                continue;
            }

            $user = $this->buildUserFromSlack($slackUser);
            $users[$user->getIdentifier()] = $user;
        }

        if ('' !== $cursor) {
            $config->setUsersPaginationParameters(['cursor' => $cursor, 'skipped_ids' => $skippedIds]);

            return $users;
        }

        // Members from other workspaces (Slack Connect) are not returned by users.list: the next batches load them
        $externalIds = array_keys(array_diff_key($memberIds, $config->getAvailableUsers(), $users, $skippedIds));

        if ([] === $externalIds) {
            $config->setUsersLoaded(true);
        } else {
            $config->setUsersPaginationParameters(['external_ids' => $externalIds, 'team_names' => []]);
        }

        return $users;
    }

    /**
     * The channels the admin can pick, following the Slack privacy model: only the channels they are a member of,
     * and private ones only when the bot is in them too.
     *
     * @return array<string, Channel>
     */
    public function listChannels(string $token, User $admin): array
    {
        $joined = $this->listConversations($token, []);
        $channels = [];

        foreach ($this->listConversations($token, ['user' => $admin->getIdentifier()]) as $id => $conversation) {
            $channels[$id] = new Channel(
                $id,
                $conversation->getName(),
                (bool) $conversation->getIsExtShared(),
                isset($joined[$id]),
            );
        }

        uasort($channels, static fn (Channel $a, Channel $b) => strnatcasecmp($a->name, $b->name));

        return $channels;
    }

    /**
     * The members of a channel as a group, external ones (Slack Connect) included.
     */
    public function loadMembers(string $token, Channel $channel): Group
    {
        $group = new Group($channel->id, '#' . $channel->name);
        $cursor = '';

        do {
            try {
                $response = $this->clientFactory->getClientForToken($token)->conversationsMembers([
                    'channel' => $channel->id,
                    'limit' => 1000,
                    'cursor' => $cursor,
                ]);
            } catch (\Throwable $t) {
                throw new UserExtractionFailedException(SlackApplication::APPLICATION_CODE, \sprintf('Could not fetch members of the channel #%s.', $channel->name), $t);
            }

            foreach ($response->getMembers() ?? [] as $userId) {
                $group->addUser($userId);
            }

            $cursor = $response->getResponseMetadata()?->getNextCursor() ?? '';
        } while ('' !== $cursor);

        return $group;
    }

    /**
     * @param array<string, Group> $channels Channels the participants are restricted to, none for the whole workspace
     *
     * @return array<string, Group>
     */
    public function extractGroups(string $token, array $channels): array
    {
        $memberIds = $this->listMemberIds($channels);
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
                if ([] === $channels || isset($memberIds[$userId])) {
                    $group->addUser($userId);
                }
            }

            $groups[$group->getIdentifier()] = $group;
        }

        return $groups + $channels;
    }

    public function getUser(string $token, string $id): User
    {
        $slackUser = $this->clientFactory->getClientForToken($token)->usersInfo([
            'user' => $id,
        ])->getUser();

        return $this->buildUserFromSlack($slackUser);
    }

    /**
     * The channels of a user, the bot itself without `user`: users.conversations omits `is_member`.
     *
     * @param array<string, mixed> $parameters
     *
     * @return array<string, ObjsConversation>
     */
    private function listConversations(string $token, array $parameters): array
    {
        $conversations = [];
        $cursor = '';

        do {
            try {
                $response = $this->clientFactory->getClientForToken($token)->usersConversations($parameters + [
                    'types' => 'public_channel,private_channel',
                    'exclude_archived' => true,
                    'limit' => 999,
                    'cursor' => $cursor,
                ]);
            } catch (\Throwable $t) {
                throw new UserExtractionFailedException(SlackApplication::APPLICATION_CODE, 'Could not fetch channels.', $t);
            }

            foreach ($response->getChannels() ?? [] as $conversation) {
                $conversations[$conversation->getId()] = $conversation;
            }

            $cursor = $response->getResponseMetadata()?->getNextCursor() ?? '';
        } while ('' !== $cursor);

        return $conversations;
    }

    /**
     * @param array<string, Group> $channels
     *
     * @return array<string, true>
     */
    private function listMemberIds(array $channels): array
    {
        $ids = [];

        foreach ($channels as $channel) {
            foreach ($channel->getUserIds() as $userId) {
                $ids[$userId] = true;
            }
        }

        return $ids;
    }

    /**
     * Slack has no endpoint to fetch several users of other workspaces at once, so each one costs a
     * users.info call: a bounded number per batch keeps the request short and under the rate limit.
     *
     * @return array<string, User>
     */
    private function loadNextBatchOfExternalUsers(string $token, Config $config): array
    {
        $pagination = $config->getUsersPaginationParameters();
        $ids = array_splice($pagination['external_ids'], 0, self::EXTERNAL_USERS_BATCH_SIZE);
        $users = [];

        foreach ($ids as $userId) {
            try {
                $slackUser = $this->clientFactory->getClientForToken($token)->usersInfo(['user' => $userId])->getUser();

                if ($slackUser->getIsBot() || $slackUser->getDeleted()) {
                    continue;
                }

                // Same person can exist in several workspaces, so show which one this account belongs to
                $pagination['team_names'][$slackUser->getTeamId()] ??= $this->clientFactory->getClientForToken($token)->teamInfo([
                    'team' => $slackUser->getTeamId(),
                ])->getTeam()->getName();
            } catch (\Throwable $t) {
                throw new UserExtractionFailedException(SlackApplication::APPLICATION_CODE, 'Could not fetch members of the shared channels.', $t);
            }

            $user = $this->buildUserFromSlack($slackUser, $pagination['team_names'][$slackUser->getTeamId()]);
            $users[$user->getIdentifier()] = $user;
        }

        $config->setUsersPaginationParameters($pagination);

        if ([] === $pagination['external_ids']) {
            $config->setUsersLoaded(true);
        }

        return $users;
    }

    /**
     * @param string|null $team Name of the workspace the user belongs to, when it is not the admin's one
     */
    private function buildUserFromSlack(ObjsUser $slackUser, ?string $team = null): User
    {
        return new User(
            $slackUser->getId(),
            $slackUser->getProfile()->getRealName(),
            [
                'nickname' => $slackUser->getName(),
                'image' => $slackUser->getProfile()->getImage192(),
                'restricted' => $slackUser->getIsRestricted(),
                'team' => $team,
            ]
        );
    }
}
