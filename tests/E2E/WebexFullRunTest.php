<?php

/*
 * This file is part of the Secret Santa project.
 *
 * (c) JoliCode <coucou@jolicode.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace JoliCode\SecretSanta\Tests\E2E;

use JoliCode\SecretSanta\Model\ApplicationToken;
use JoliCode\SecretSanta\Model\User;
use JoliCode\SecretSanta\Tests\Fake\FakeChatApi;
use JoliCode\SecretSanta\Tests\Fake\FakeWebexApi;

class WebexFullRunTest extends FullRunTestCase
{
    protected const NAMES = ['W1' => 'Alice', 'W2' => 'Bob', 'W3' => 'Carol', 'W4' => 'Dave', 'W5' => 'Eve'];

    protected static function application(): string
    {
        return 'webex';
    }

    protected function sessionAttributes(): array
    {
        // Same keys as WebexApplication::SESSION_KEY_TOKEN / SESSION_KEY_ADMIN
        return [
            'santa.webex.token' => new ApplicationToken('webex-fake', ['team' => 'JoliCode']),
            'santa.webex.admin' => new User('W1', 'Alice'),
        ];
    }

    protected function fakeApi(): FakeChatApi
    {
        return static::getContainer()->get(FakeWebexApi::class);
    }

    protected function receiverOf(string $message): string
    {
        // Webex messages name the receiver without any id
        self::assertMatchesRegularExpression('/🎁 \*\*(\w+)\*\* 🎁/u', $message, 'Secret message names a receiver');
        preg_match('/🎁 \*\*(\w+)\*\* 🎁/u', $message, $matches);

        return (string) array_search($matches[1], self::NAMES, true);
    }
}
