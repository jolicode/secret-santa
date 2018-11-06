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

use JoliCode\SecretSanta\Exception\UserExtractionFailedException;
use JoliCode\SecretSanta\Model\Group;
use JoliCode\SecretSanta\Model\User;
use RestCord\Model\Guild\GuildMember;
use RestCord\Model\Guild\Role;

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
    public function extractForGuild(int $guildId): array
    {
        try {
            /** @var GuildMember[] $members */
            $members = $this->apiHelper->getMembersInGuild($guildId);
        } catch (\Throwable $t) {
            throw new UserExtractionFailedException('Could not fetch members in guild.', 0, $t);
        }

        $members = array_filter($members, function (GuildMember $member) {
            return !$member->user->bot;
        });

        $users = [];

        foreach ($members as $member) {
            $user = new User(
                $member->user->id,
                $member->user->username,
                [
                    'nickname' => $member->nick ?? null,
                    'image' => $member->user->avatar ? sprintf('https://cdn.discordapp.com/avatars/%s/%s.png', $member->user->id, $member->user->avatar) : null,
                    'groups' => (array) $member->roles,
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
    public function extractGroupsForGuild(int $guildId): array
    {
        /** @var Role[] $roles */
        $roles = $this->apiHelper->getRolesInGuild($guildId);

        $groups = [];

        foreach ($roles as $role) {
            if ('@everyone' === $role->name) {
                continue;
            }

            $group = new Group(
                $role->id,
                $role->name
            );

            $groups[$group->getIdentifier()] = $group;
        }

        uasort($groups, function (Group $a, Group $b) {
            return strnatcasecmp($a->getName(), $b->getName());
        });

        return $groups;
    }
}
