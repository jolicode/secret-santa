<?php

/*
 * This file is part of the Secret Santa project.
 *
 * (c) JoliCode <coucou@jolicode.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace JoliCode\SecretSanta\Microsoft;

use JoliCode\SecretSanta\Exception\UserExtractionFailedException;
use JoliCode\SecretSanta\User;

class UserExtractor
{
    private $apiHelper;

    public function __construct(ApiHelper $apiHelper)
    {
        $this->apiHelper = $apiHelper;
    }

    public function extractAll(Configuration $configuration): array
    {
        try {
            $members = $this->apiHelper->getMembers($configuration);
        } catch (\Throwable $t) {
            throw new UserExtractionFailedException('Could not fetch members in team', 0, $t);
        }

        $users = [];

        foreach ($members as $member) {
            dump($member);
            $user = new User(
                $member['id'],
                $member['name'],
                [
//                    'nickname' => $member->nick ?? null,
//                    'image' => $member->user->avatar ? sprintf('https://cdn.discordapp.com/avatars/%s/%s.png', $member->user->id, $member->user->avatar) : null,
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
