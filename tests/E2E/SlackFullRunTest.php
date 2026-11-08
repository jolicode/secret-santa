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
use JoliCode\SecretSanta\Tests\Fake\FakeSlackApi;
use PHPUnit\Framework\Attributes\Group;

#[Group('screenshot')]
class SlackFullRunTest extends FullRunTestCase
{
    protected const NAMES = ['U1' => 'Alice', 'U2' => 'Bob', 'U3' => 'Carol', 'U4' => 'Dave', 'U5' => 'Eve'];

    /**
     * The channel step before the journey: the selection, and the invitation asked for a shared
     * channel the bot is not in.
     */
    public function testChannelsStep(): void
    {
        $this->logInAsAdmin();

        $this->visit('/auth/slack/channels');
        // The checkbox itself is styled away, the label is what gets clicked
        $this->getPage()->locator('label[for="channel_C_SANTA"]')->click();
        $this->getPage()->locator('label[for="channel_C_OTHER"]')->click();
        $this->checkpoint('channels');

        $this->getPage()->locator('#submit-button')->click();
        $this->followRedirect();
        self::assertStringEndsWith('/auth/slack/channels/invitation', $this->getPage()->url());
        $this->checkpoint('channels-invitation');
    }

    protected static function application(): string
    {
        return 'slack';
    }

    protected function sessionAttributes(): array
    {
        // Same keys as SlackApplication::SESSION_KEY_TOKEN / SESSION_KEY_ADMIN
        return [
            'santa.slack.token' => new ApplicationToken('xoxb-fake', ['team' => 'JoliCode']),
            'santa.slack.admin' => new User('U1', 'Alice', ['nickname' => 'alice', 'image' => 'https://localhost/images/logo.png?user=alice', 'restricted' => false]),
        ];
    }

    protected function fakeApi(): FakeChatApi
    {
        return static::getContainer()->get(FakeSlackApi::class);
    }

    protected function receiverOf(string $message): string
    {
        self::assertMatchesRegularExpression('/\*<@(U\d)>\* :gift:/', $message, 'Secret message names a receiver');
        preg_match('/\*<@(U\d)>\* :gift:/', $message, $matches);

        return $matches[1];
    }
}
