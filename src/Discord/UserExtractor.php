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
use JoliCode\SecretSanta\User;
use RestCord\Model\Guild\GuildMember;

class UserExtractor
{
    /** @var DiscordService */
    private $discordService;

    public function __construct(DiscordService $discordService)
    {
        $this->discordService = $discordService;
    }

    public function extractForGuild(int $guildId): array
    {
        try {
            /** @var GuildMember[] $members */
            $members = $this->discordService->getMembersInGuild($guildId);
        } catch (\Throwable $t) {
            throw new UserExtractionFailedException('Could not fetch members in guild', 0, $t);
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
