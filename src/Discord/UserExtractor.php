<?php

/*
 * This file is part of the Secret Santa project.
 *
 * (c) JoliCode <coucou@jolicode.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace JoliCode\SecretSanta\Discord;

use JoliCode\SecretSanta\Application\DiscordApplication;
use JoliCode\SecretSanta\Exception\UserExtractionFailedException;
use JoliCode\SecretSanta\Model\Config;
use JoliCode\SecretSanta\Model\Group;
use JoliCode\SecretSanta\Model\User;

class UserExtractor
{
    public function __construct(private ApiHelper $apiHelper)
    {
    }

    /**
     * @return array<string|int, User>
     */
    public function extractForGuild(int $guildId, Config $config): array
    {
        if ($config->areUsersLoaded()) {
            return [];
        }

        $after = $config->getUsersPaginationParameters()['after'] ?? null;

        try {
            $members = $this->apiHelper->getMembersInGuild($guildId, $after);
        } catch (\Throwable $t) {
            throw new UserExtractionFailedException(DiscordApplication::APPLICATION_CODE, 'Could not fetch members in guild.', $t);
        }

        $lastMember = $members ? end($members) : null;

        $config->setUsersPaginationParameters([
            'after' => $lastMember['user']['id'] ?? null,
        ]);

        if (!$members) {
            $config->setUsersLoaded(true);
        }

        $users = [];

        foreach ($members as $member) {
            if ($member['user']['bot'] ?? false) {
                continue;
            }

            $user = new User(
                (string) $member['user']['id'],
                $member['user']['username'],
                [
                    'nickname' => $member['nick'] ?? null,
                    'image' => ($member['user']['avatar'] ?? null) ? \sprintf('https://cdn.discordapp.com/avatars/%s/%s.png', $member['user']['id'], $member['user']['avatar']) : null,
                    'groups' => (array) $member['roles'],
                ]
            );

            $users[$user->getIdentifier()] = $user;
        }

        return $users;
    }

    /**
     * @return array<string|int, Group>
     */
    public function extractGroupsForGuild(int $guildId): array
    {
        $roles = $this->apiHelper->getRolesInGuild($guildId);

        $groups = [];

        foreach ($roles as $role) {
            if ('@everyone' === $role['name']) {
                continue;
            }

            $group = new Group(
                (string) $role['id'],
                $role['name']
            );

            $groups[$group->getIdentifier()] = $group;
        }

        uasort($groups, function (Group $a, Group $b) {
            return strnatcasecmp($a->getName(), $b->getName());
        });

        return $groups;
    }
}
