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

use JoliCode\SecretSanta\Exception\RudolphException;
use JoliCode\SecretSanta\Model\Config;
use JoliCode\SecretSanta\Model\User;

/**
 * Rudolph is the Reindeer guiding Santa.
 *
 * It will help Santa by deciding whom which user should offer a gift to.
 *
 * @see https://en.wikipedia.org/wiki/Rudolph_the_Red-Nosed_Reindeer
 */
class Rudolph
{
    private const int MAX_ATTEMPTS = 10;

    /**
     * @return array<string|int, string|int>
     */
    public function associateUsers(Config $config): array
    {
        $users = $config->getSelectedUsers();
        $exclusions = array_filter($config->getExclusions());

        $this->assertUserListCorrect($config, $users);
        $this->assertExclusionsValid($config, $users, $exclusions);

        mt_srand();

        // Simple path: no exclusions, just shuffle and assign in a circle
        if (!$exclusions) {
            $associations = [];
            $userCount = \count($users);

            shuffle($users);

            for ($i = 1; $i < $userCount; ++$i) {
                $associations[$users[$i - 1]] = $users[$i];
                $associations[$users[$userCount - 1]] = $users[0];
            }

            $associations[$users[$userCount - 1]] = $users[0];

            return $associations;
        }

        $users = array_values($users);

        for ($attempt = 0; $attempt < self::MAX_ATTEMPTS; ++$attempt) {
            $shuffled = $users;
            shuffle($shuffled);

            $used = array_fill_keys($shuffled, false);
            $result = $this->assignSantaRecursive($shuffled, $exclusions, $used, []);

            if (null !== $result) {
                return $result;
            }
        }

        throw new RudolphException('Unable to find a valid Secret Santa assignment after multiple attempts. Please check the exclusions and try again.');
    }

    /**
     * Recursive function to assign Secret Santa pairs using backtracking algorithm.
     *
     * @param list<string|int>                    $users
     * @param array<string|int, list<string|int>> $exclusions
     * @param array<string|int, bool>             $used
     * @param array<string|int, string|int>       $current
     *
     * @return array<string|int, string|int>|null
     */
    private function assignSantaRecursive(array $users, array $exclusions, array &$used, array $current): ?array
    {
        $index = \count($current);

        // All users have been assigned
        if ($index === \count($users)) {
            return $current;
        }

        $giver = $users[$index];

        // Find possible receivers for the current giver
        $possibleReceivers = array_filter($users, function ($receiver) use ($giver, $exclusions, $used) {
            return !$used[$receiver]
                && $receiver !== $giver
                && !\in_array($receiver, $exclusions[$giver] ?? [], true);
        });

        // Randomize possible receivers to ensure different results on each run
        $possibleReceivers = array_values($possibleReceivers);
        shuffle($possibleReceivers);

        foreach ($possibleReceivers as $receiver) {
            $used[$receiver] = true;
            $current[$giver] = $receiver;

            $result = $this->assignSantaRecursive($users, $exclusions, $used, $current);
            if (null !== $result) {
                return $result;
            }

            // Backtrack
            unset($current[$giver]);
            $used[$receiver] = false;
        }

        // No valid assignment found for this user
        return null;
    }

    /**
     * @param list<string|int> $users
     */
    private function assertUserListCorrect(Config $config, array $users): void
    {
        $filteredUsers = array_unique($users);

        if (\count($filteredUsers) !== \count($users)) {
            $duplicated = array_unique(array_diff_key($users, $filteredUsers));
            $duplicated = array_map(function ($userId) use ($config) {
                $user = $config->getUser($userId);

                return $user ? $user->getName() : (string) $userId;
            }, $duplicated);

            throw new RudolphException(\sprintf('%s more than one time in the list: %s.', \count($duplicated) > 1 ? 'These users were' : 'This user was', implode(', ', $duplicated)));
        }

        if (\count($filteredUsers) < 2) {
            throw new RudolphException(\sprintf('Expected at least 2 users in the list, %s given.', \count($filteredUsers)));
        }
    }

    /**
     * @param list<string|int>                    $users
     * @param array<string|int, list<string|int>> $exclusions
     */
    private function assertExclusionsValid(Config $config, array $users, array $exclusions): void
    {
        $userSet = array_fill_keys($users, true);

        // Verify that no user excludes everyone else
        foreach ($users as $user) {
            $excluded = $exclusions[$user] ?? [];

            // Remove invalid or non-existing exclusions (not present in the list)
            $validExcluded = array_values(array_intersect($excluded, $users));

            // Valid receivers = all except oneself and one's exclusions
            $possibleReceivers = array_diff($users, [$user], $validExcluded);

            if (0 === \count($possibleReceivers)) {
                throw new RudolphException(\sprintf('User "%s" has no possible receiver (exclusions are too restrictive). Please check the exclusions.', $config->getUser($user)?->getName() ?? (string) $user));
            }
        }

        // Also check if someone is excluded by everyone (extreme case)
        foreach ($users as $receiver) {
            $forbiddenForAll = true;
            foreach ($users as $giver) {
                if ($giver === $receiver) {
                    continue;
                }

                $excluded = $exclusions[$giver] ?? [];
                if (!\in_array($receiver, $excluded, true)) {
                    $forbiddenForAll = false;

                    break;
                }
            }

            if ($forbiddenForAll) {
                throw new RudolphException(\sprintf('User "%s" cannot receive from anyone. Please check the exclusions.', $config->getUser($receiver)?->getName() ?? (string) $receiver));
            }
        }
    }
}
