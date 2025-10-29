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

use JoliCode\SecretSanta\Model\Config;
use JoliCode\SecretSanta\Model\SecretSanta;
use JoliCode\SecretSanta\Model\User;
use JoliCode\SecretSanta\Santa\Spoiler;
use PHPUnit\Framework\TestCase;

class SpoilerTest extends TestCase
{
    private Spoiler $SUT;

    protected function setUp(): void
    {
        $this->SUT = new Spoiler();
    }

    public function testItEncodesSecretSantaInCurrentVersion(): void
    {
        $config = new Config('my_application', 'toto', null);
        $config->setAvailableUsers(
            [
                'user1' => new User('user1', 'User 1'),
                'user2' => new User('user2', 'User 2'),
                'user3' => new User('user3', 'User 3'),
                'user4' => new User('user4', 'User 4'),
                'user5' => new User('user5', 'User 5'),
            ]
        );
        $config->setSelectedUsers(['user1', 'user2', 'user3', 'user4', 'user5']);
        $config->setMessage('hello test');

        $secretSanta = new SecretSanta('yolo', [
            'user1' => 'user2',
            'user3' => 'user4',
            'user2' => 'user3',
            'user5' => 'user1',
            'user4' => 'user5',
        ], $config);

        $code = $this->SUT->encode($secretSanta);
        $expectedCode = 'v3@H4sIAAAAAAAAA4tWCi1OLVIwVNKBMIxgDGMYwwTGMFWKBQAbdZzDLgAAAA==';

        self::assertSame($expectedCode, $code);
    }

    public function testItDecodesInV1(): void
    {
        $code = 'v1@eyJ1c2VyMSI6InVzZXIyIiwidXNlcjIiOiJ1c2VyMSJ9';

        $repartition = $this->SUT->decode($code);
        $expectedRepartition = [
            '@user1' => '@user2',
            '@user2' => '@user1',
        ];

        self::assertSame($expectedRepartition, $repartition);
    }

    public function testItDecodesInV2(): void
    {
        $code = 'v2@eyJVc2VyIDEiOiJVc2VyIDIiLCJVc2VyIDIiOiJVc2VyIDMiLCJVc2VyIDMiOiJVc2VyIDQiLCJVc2VyIDQiOiJVc2VyIDUiLCJVc2VyIDUiOiJVc2VyIDEifQ==';

        $repartition = $this->SUT->decode($code);
        $expectedRepartition = [
            'User 1' => 'User 2',
            'User 2' => 'User 3',
            'User 3' => 'User 4',
            'User 4' => 'User 5',
            'User 5' => 'User 1',
        ];

        self::assertSame($expectedRepartition, $repartition);
    }

    public function testItDecodesInV3(): void
    {
        $code = 'v3@H4sIAAAAAAAAA4tWCi1OLVIwVNKBMIxgDGMYwwTGMFWKBQAbdZzDLgAAAA==';

        $repartition = $this->SUT->decode($code);
        $expectedRepartition = [
            'User 1' => 'User 2',
            'User 2' => 'User 3',
            'User 3' => 'User 4',
            'User 4' => 'User 5',
            'User 5' => 'User 1',
        ];

        self::assertSame($expectedRepartition, $repartition);
    }

    public function testItFailsToDecodeInWrongVersion(): void
    {
        $code = 'v0@eyJ1c2VyMSI6InVzZXIyIiwidXNlcjIiOiJ1c2VyMSJ9';

        $repartition = $this->SUT->decode($code);

        self::assertNull($repartition);
    }

    public function testItFailsToDecodeInvalidCode(): void
    {
        $code = 'v2@azerty';

        $repartition = $this->SUT->decode($code);

        self::assertNull($repartition);
    }
}
