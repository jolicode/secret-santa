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
use JoliCode\SecretSanta\Tests\Fake\FakeDiscordApi;

class DiscordFullRunTest extends FullRunTestCase
{
    // Discord shows usernames, the nick of Bob is only used in the messages
    protected const NAMES = ['101' => 'alice', '102' => 'bob', '103' => 'carol', '104' => 'dave', '105' => 'eve'];

    protected static function application(): string
    {
        return 'discord';
    }

    protected function sessionAttributes(): array
    {
        // Same keys as DiscordApplication::SESSION_KEY_TOKEN / SESSION_KEY_ADMIN
        return [
            'santa.discord.token' => new ApplicationToken('discord-fake', ['guildId' => 42, 'guildName' => 'JoliCode']),
            'santa.discord.admin' => new User('101', 'alice', ['nickname' => null, 'image' => null, 'groups' => []]),
        ];
    }

    protected function fakeApi(): FakeChatApi
    {
        return static::getContainer()->get(FakeDiscordApi::class);
    }

    protected function receiverOf(string $message): string
    {
        self::assertMatchesRegularExpression('/:gift: \*\*\w+ \(<@!(\d+)>\)\*\* :gift:/', $message, 'Secret message names a receiver');
        preg_match('/:gift: \*\*\w+ \(<@!(\d+)>\)\*\* :gift:/', $message, $matches);

        return $matches[1];
    }

    /**
     * @param array{to: string, text: string, file?: string|null} $adminMessage
     */
    protected function spoilCode(array $adminMessage): string
    {
        // The code is attached as a file to the Discord admin message
        self::assertStringContainsString('secret-santa-spoil-code.txt', $adminMessage['text']);
        $file = $adminMessage['file'] ?? null;
        self::assertNotNull($file, 'The spoil code is attached as a file');

        return $file;
    }
}
