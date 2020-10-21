<?php

/*
 * This file is part of the Secret Santa project.
 *
 * (c) JoliCode <coucou@jolicode.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace JoliCode\SecretSanta\Santa;

/**
 * Rudolph is the Reindeer guiding Santa.
 *
 * It will help Santa by deciding whom which user should offer a gift to.
 *
 * @see https://en.wikipedia.org/wiki/Rudolph_the_Red-Nosed_Reindeer
 */
class Rudolph
{
    /**
     * @param string[] $users
     *
     * @return array<string, string>
     */
    public function associateUsers(array $users): array
    {
        $this->assertUserListCorrect($users);

        $userCount = \count($users);
        $associations = [];

        mt_srand();
        shuffle($users);

        for ($i = 1; $i < $userCount; ++$i) {
            $associations[$users[$i - 1]] = $users[$i];
        }

        $associations[$users[$userCount - 1]] = $users[0];

        return $associations;
    }

    /**
     * @param string[] $users
     */
    private function assertUserListCorrect(array $users): void
    {
        $filteredUsers = array_unique($users);

        if (\count($filteredUsers) !== \count($users)) {
            $duplicated = array_unique(array_diff_key($users, $filteredUsers));
            throw new \LogicException(sprintf('%s more than one time in the list: %s.', \count($duplicated) > 1 ? 'These users were' : 'This user was', implode(', ', $duplicated)));
        }

        if (\count($filteredUsers) < 2) {
            throw new \LogicException(sprintf('Expected at least 2 users in the list, %s given.', \count($filteredUsers)));
        }
    }
}
