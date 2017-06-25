<?php

namespace Joli\SlackSecretSanta\Tests;

use Joli\SlackSecretSanta\SecretSanta;
use Joli\SlackSecretSanta\Spoiler;
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
        $secretSanta = new SecretSanta('yolo', [
            'user1' => 'user2',
            'user2' => 'user1'
        ], null, null);

        $code = $this->SUT->encode($secretSanta);
        $expectedCode = 'v1@eyJ1c2VyMSI6InVzZXIyIiwidXNlcjIiOiJ1c2VyMSJ9';

        self::assertSame($expectedCode, $code);
    }

    public function test_it_decodes_in_v1()
    {
        $code = 'v1@eyJ1c2VyMSI6InVzZXIyIiwidXNlcjIiOiJ1c2VyMSJ9';

        $repartition = $this->SUT->decode($code);
        $expectedRepartition = [
            'user1' => 'user2',
            'user2' => 'user1'
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
        $code = 'v1@azerty';

        $repartition = $this->SUT->decode($code);

        self::assertNull($repartition);
    }
}
