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

use JoliCode\SecretSanta\Model\SecretSanta;
use JoliCode\SecretSanta\Model\User;
use JoliCode\SecretSanta\Santa\Spoiler;
use PHPUnit\Framework\TestCase;

class SpoilerTest extends TestCase
{
    /** @var Spoiler */
    private $SUT;

    protected function setUp(): void
    {
        $this->SUT = new Spoiler();
    }

    public function test_it_encodes_secret_santa_in_current_version(): void
    {
        $secretSanta = new SecretSanta('my_application', 'toto', 'yolo', [
            'user1' => new User('user1', 'User 1'),
            'user2' => new User('user2', 'User 2'),
            'user3' => new User('user3', 'User 3'),
            'user4' => new User('user4', 'User 4'),
            'user5' => new User('user5', 'User 5'),
        ], [
            'user1' => 'user2',
            'user2' => 'user3',
            'user3' => 'user4',
            'user4' => 'user5',
            'user5' => 'user1',
        ], null, null);

        $code = $this->SUT->encode($secretSanta);
        $expectedCode = 'v3@H4sIAAAAAAAAA4tWCi1OLVIwVNKBMIxgDGMYwwTGMFWKBQAbdZzDLgAAAA==';

        self::assertSame($expectedCode, $code);
    }

    public function test_it_decodes_in_v1(): void
    {
        $code = 'v1@eyJ1c2VyMSI6InVzZXIyIiwidXNlcjIiOiJ1c2VyMSJ9';

        $repartition = $this->SUT->decode($code);
        $expectedRepartition = [
            '@user1' => '@user2',
            '@user2' => '@user1',
        ];

        self::assertSame($expectedRepartition, $repartition);
    }

    public function test_it_decodes_in_v2(): void
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

    public function test_it_decodes_in_v3(): void
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

    public function test_it_fails_to_decode_in_wrong_version(): void
    {
        $code = 'v0@eyJ1c2VyMSI6InVzZXIyIiwidXNlcjIiOiJ1c2VyMSJ9';

        $repartition = $this->SUT->decode($code);

        self::assertNull($repartition);
    }

    public function test_it_fails_to_decode_invalid_code(): void
    {
        $code = 'v2@azerty';

        $repartition = $this->SUT->decode($code);

        self::assertNull($repartition);
    }
}
