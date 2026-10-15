<?php

/*
 * This file is part of the Secret Santa project.
 *
 * (c) JoliCode <coucou@jolicode.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace JoliCode\SecretSanta\Tests\Controller;

use JoliCode\SecretSanta\Model\ApplicationToken;
use JoliCode\SecretSanta\Model\Config;
use JoliCode\SecretSanta\Model\User;

class MessageNoteErrorTest extends BaseWebTestCase
{
    use SessionPrepareTrait;

    public function testTooLongNoteErrorNamesTheParticipant(): void
    {
        $client = static::createClient();

        $config = new Config('slack', 'toto', null);
        $config->setAvailableUsers(['toto1' => new User('toto1', 'Toto 1', ['image' => '', 'nickname' => '']), 'toto2' => new User('toto2', 'Toto 2', ['image' => '', 'nickname' => ''])]);
        $config->setSelectedUsers(['toto1', 'toto2']);

        $this->prepareSession($client, [
            'santa.slack.token' => new ApplicationToken('token', ['team' => 'team']),
            'santa.slack.admin' => new User('toto1', 'Toto 1'),
            'config' => $config,
        ]);

        $crawler = $client->request('POST', '/message/slack', ['message' => ['message' => '', 'notes' => ['toto1' => '', 'toto2' => str_repeat('a', 401)]]]);

        self::assertSame(200, $client->getResponse()->getStatusCode());
        self::assertStringContainsString('Each note should contain less than 400 characters (note for Toto 2)', $crawler->text());
    }
}
