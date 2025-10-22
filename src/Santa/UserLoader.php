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
        if (!$config->getAvailableUsers()) {
            $config->setGroups($application->getGroups());
        }

        $users = $application->loadNextBatchOfUsers($config);

        // Our HTTP client might respect rate limits but just in case, let's be gentle and not spam requests.
        usleep(100000);

        $config->setAvailableUsers(array_merge($config->getAvailableUsers(), $users));

        $users = $config->getAvailableUsers();

        uasort($users, function (User $a, User $b) {
            return strnatcasecmp($a->getName(), $b->getName());
        });

        $config->setAvailableUsers($users);
    }
}
