<?php

/*
 * This file is part of the Secret Santa project.
 *
 * (c) JoliCode <coucou@jolicode.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace JoliCode\SecretSanta\Tests\Santa;

use JoliCode\SecretSanta\Application\ApplicationInterface;
use JoliCode\SecretSanta\Exception\UserExtractionFailedException;
use JoliCode\SecretSanta\Model\Config;
use JoliCode\SecretSanta\Model\Group;
use JoliCode\SecretSanta\Model\User;
use JoliCode\SecretSanta\Santa\UserLoader;
use PHPUnit\Framework\TestCase;

class UserLoaderTest extends TestCase
{
    public function testGroupsFailureIsWrappedInUserExtractionFailedException(): void
    {
        $application = $this->createMock(ApplicationInterface::class);
        $application->method('getCode')->willReturn('discord');
        $application->method('getGroups')->willThrowException(new \RuntimeException('Failed to retrieve roles: {"message": "Unknown Guild", "code": 10004}'));

        $this->expectException(UserExtractionFailedException::class);

        (new UserLoader())->loadUsers(new Config('discord', 'toto', null), $application);
    }

    public function testUsersAreLinkedToGroupsAcrossBatches(): void
    {
        $config = new Config('discord', 'toto', null);
        $batches = [
            ['toto1' => new User('toto1', 'Toto 1', ['groups' => ['role1']])],
            ['toto2' => new User('toto2', 'Toto 2', ['groups' => ['role1', 'unknown']]), 'toto3' => new User('toto3', 'Toto 3')],
        ];

        $application = $this->createMock(ApplicationInterface::class);
        $application->method('getGroups')->willReturn(['role1' => new Group('role1', 'Role 1'), 'role2' => new Group('role2', 'Role 2')]);
        $application->method('loadNextBatchOfUsers')->willReturnCallback(function () use (&$batches) {
            return array_shift($batches);
        });

        $loader = new UserLoader();
        $loader->loadUsers($config, $application);
        // Simulate a new request: the config comes back from the session
        $config = unserialize(serialize($config));
        $loader->loadUsers($config, $application);

        self::assertSame(['toto1', 'toto2', 'toto3'], array_keys($config->getAvailableUsers()));
        self::assertSame(['toto1', 'toto2'], $config->getGroups()['role1']->getUserIds());
        self::assertSame([], $config->getGroups()['role2']->getUserIds());
    }
}
