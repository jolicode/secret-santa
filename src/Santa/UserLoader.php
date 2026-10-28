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

use JoliCode\SecretSanta\Application\ApplicationInterface;
use JoliCode\SecretSanta\Exception\UserExtractionFailedException;
use JoliCode\SecretSanta\Model\Config;
use JoliCode\SecretSanta\Model\User;

class UserLoader
{
    /**
     * Load users from the application.
     *
     * @throws UserExtractionFailedException
     */
    public function loadUsers(Config $config, ApplicationInterface $application): void
    {
        $firstBatch = !$config->getAvailableUsers();

        $users = $application->loadNextBatchOfUsers($config);

        // Some applications (Webex) only know their groups once the users are loaded
        if ($firstBatch) {
            try {
                $config->setGroups($application->getGroups());
            } catch (\Throwable $t) {
                throw new UserExtractionFailedException($application->getCode(), 'Could not fetch groups.', $t);
            }
        }

        // Some applications (Discord) give the groups of each user instead of the users of each group.
        // The config groups are the ones persisted in session, so this works across batches.
        $groups = $config->getGroups();
        foreach ($users as $user) {
            foreach ($user->getExtra()['groups'] ?? [] as $groupId) {
                if (isset($groups[$groupId])) {
                    $groups[$groupId]->addUser($user->getIdentifier());
                }
            }
        }

        // Our HTTP client might respect rate limits but just in case, let's be gentle and not spam requests.
        usleep(100000);

        $config->setAvailableUsers($config->getAvailableUsers() + $users);

        $users = $config->getAvailableUsers();

        uasort($users, static function (User $a, User $b) {
            return strnatcasecmp($a->getName(), $b->getName());
        });

        $config->setAvailableUsers($users);
    }
}
