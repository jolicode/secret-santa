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
}
