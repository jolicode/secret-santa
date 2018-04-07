<?php

/*
 * This file is part of the Slack Secret Santa project.
 *
 * (c) JoliCode <coucou@jolicode.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Joli\SlackSecretSanta\Tests;

use Joli\SlackSecretSanta\SecretSanta;
use Joli\SlackSecretSanta\Spoiler;
use Joli\SlackSecretSanta\User;
use PHPUnit\Framework\TestCase;

class SpoilerTest extends TestCase
{
    /** @var Spoiler */
    private $SUT;

    protected function setUp()
    {
        $this->SUT = new Spoiler();
    }

    public function test_it_encodes_secret_santa_in_current_version()
    {
        $secretSanta = new SecretSanta('my_application', 'toto', 'yolo', [
            'user1' => new User('user1', 'User 1'),
            'user2' => new User('user2', 'User 2'),
        ], [
            'user1' => 'user2',
            'user2' => 'user1',
        ], null, null);

        $code = $this->SUT->encode($secretSanta);
        $expectedCode = 'v2@eyJVc2VyIDEiOiJVc2VyIDIiLCJVc2VyIDIiOiJVc2VyIDEifQ==';

        self::assertSame($expectedCode, $code);
    }

    public function test_it_decodes_in_v1()
    {
        $code = 'v1@eyJ1c2VyMSI6InVzZXIyIiwidXNlcjIiOiJ1c2VyMSJ9';

        $repartition = $this->SUT->decode($code);
        $expectedRepartition = [
            '@user1' => '@user2',
            '@user2' => '@user1',
        ];

        self::assertSame($expectedRepartition, $repartition);
    }

    public function test_it_decodes_in_v2()
    {
        $code = 'v2@eyJVc2VyIDEiOiJVc2VyIDIiLCJVc2VyIDIiOiJVc2VyIDEifQ==';

        $repartition = $this->SUT->decode($code);
        $expectedRepartition = [
            'User 1' => 'User 2',
            'User 2' => 'User 1',
        ];

        self::assertSame($expectedRepartition, $repartition);
    }

    public function test_it_fails_to_decode_in_wrong_version()
    {
        $code = 'v0@eyJ1c2VyMSI6InVzZXIyIiwidXNlcjIiOiJ1c2VyMSJ9';

        $repartition = $this->SUT->decode($code);

        self::assertNull($repartition);
    }

    public function test_it_fails_to_decode_invalid_code()
    {
        $code = 'v2@azerty';

        $repartition = $this->SUT->decode($code);

        self::assertNull($repartition);
    }
}
