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

use JoliCode\SecretSanta\Exception\RudolphException;
use JoliCode\SecretSanta\Model\Config;
use JoliCode\SecretSanta\Model\User;
use JoliCode\SecretSanta\Santa\Rudolph;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class RudolphTest extends TestCase
{
    private Rudolph $SUT;

    protected function setUp(): void
    {
        $this->SUT = new Rudolph();
    }

    #[DataProvider('exceptionsDataProvider')]
    public function testCheckExceptions(Config $config, string $expectedExceptionMessage): void
    {
        $this->expectException(RudolphException::class);
        $this->expectExceptionMessage($expectedExceptionMessage);

        $this->SUT->associateUsers($config);
    }

    /**
     * @return iterable<string, array{0: Config, 1: string}>
     */
    public static function exceptionsDataProvider(): iterable
    {
        $config = new Config('app', 'org', null);
        $config->setAvailableUsers([
            'user1' => new User('user1', 'User 1'),
            'user2' => new User('user2', 'User 2'),
            'user3' => new User('user3', 'User 3'),
            'user4' => new User('user4', 'User 4'),
        ]);
        $config->setUsersLoaded(true);

        $testConfig = clone $config;
        $testConfig->setSelectedUsers([]);
        yield 'empty list' => [
            $testConfig,
            'Expected at least 2 users in the list, 0 given.',
        ];

        $testConfig = clone $config;
        $testConfig->setSelectedUsers(['user1']);
        yield 'list with 1 user' => [
            $testConfig,
            'Expected at least 2 users in the list, 1 given.',
        ];

        $testConfig = clone $config;
        $testConfig->setSelectedUsers(['user1', 'user2', 'user3', 'user2']);
        yield 'one duplicate' => [
            $testConfig,
            'This user was more than one time in the list: User 2.',
        ];

        $testConfig = clone $config;
        $testConfig->setSelectedUsers(['user1', 'user2', 'user3', 'user2', 'user1']);
        yield 'several duplicate' => [
            $testConfig,
            'These users were more than one time in the list: User 2, User 1.',
        ];

        $testConfig = clone $config;
        $testConfig->setSelectedUsers(['user1', 'user2', 'user3']);
        $testConfig->setExclusions([
            'user1' => ['user2', 'user3'],
        ]);
        yield 'no possible receiver' => [
            $testConfig,
            'User "User 1" has no possible receiver (exclusions are too restrictive). Please check the exclusions.',
        ];

        $testConfig = clone $config;
        $testConfig->setSelectedUsers(['user1', 'user2', 'user3']);
        $testConfig->setExclusions([
            'user1' => ['user3'],
            'user2' => ['user3'],
        ]);
        yield 'no possible santa' => [
            $testConfig,
            'User "User 3" cannot receive from anyone. Please check the exclusions.',
        ];

        $testConfig = clone $config;
        $testConfig->setSelectedUsers(['user1', 'user2', 'user3']);
        $testConfig->setExclusions([
            'user1' => ['user2'],
            'user2' => ['user1'],
        ]);
        yield 'no solution' => [
            $testConfig,
            'Unable to find a valid Secret Santa assignment after multiple attempts. Please check the exclusions and try again.',
        ];
    }

    #[DataProvider('userListDataProvider')]
    public function testItCreateAssociations(Config $config): void
    {
        $associations = $this->SUT->associateUsers($config);

        $this->assertCount(\count($config->getSelectedUsers()), $associations);

        foreach ($config->getSelectedUsers() as $user) {
            self::assertArrayHasKey($user, $associations);
            self::assertContains($user, $associations);
            self::assertNotSame($user, $associations[$user]);
        }
    }

    /**
     * @return iterable<string, array{0: Config}>
     */
    public static function userListDataProvider(): iterable
    {
        $config = new Config('app', 'org', null);
        $config->setAvailableUsers([
            'user1' => new User('user1', 'User 1'),
            'user2' => new User('user2', 'User 2'),
            'user3' => new User('user3', 'User 3'),
            'user4' => new User('user4', 'User 4'),
            'user5' => new User('user5', 'User 5'),
            'user6' => new User('user6', 'User 6'),
            'user7' => new User('user7', 'User 7'),
            'user8' => new User('user8', 'User 8'),
            'user9' => new User('user9', 'User 9'),
            'user10' => new User('user10', 'User 10'),
        ]);
        $config->setUsersLoaded(true);

        $testConfig = clone $config;
        $testConfig->setSelectedUsers([
            'user1',
            'user2',
        ]);
        yield 'two users' => [$testConfig];

        $testConfig = clone $config;
        $testConfig->setSelectedUsers([
            'user1',
            'user2',
            'user3',
        ]);
        yield 'three users' => [$testConfig];

        $testConfig = clone $config;
        $testConfig->setSelectedUsers([
            'user1',
            'user2',
            'user3',
            'user4',
            'user5',
            'user6',
            'user7',
            'user8',
            'user9',
            'user10',
        ]);
        yield 'ten users' => [$testConfig];

        $testConfig = clone $config;
        $testConfig->setSelectedUsers([
            'user1',
            'user2',
            'user3',
        ]);
        $testConfig->setExclusions([
            'user1' => ['user2'],
            'user2' => ['user3'],
        ]);
        yield 'with exclusions' => [$testConfig];
    }
}
