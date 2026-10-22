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
use Symfony\Bundle\FrameworkBundle\KernelBrowser;

class MessageScheduleTest extends BaseWebTestCase
{
    use SessionPrepareTrait;

    public function testScheduledMessageIsAccepted(): void
    {
        $client = static::createClient();
        $this->prepareSlackSession($client);

        $client->request('POST', '/message/slack', ['message' => ['message' => '', 'notes' => ['toto1' => '', 'toto2' => ''], 'options' => ['scheduled_at' => time() + 3600], '_token' => 'csrf-token']], [], ['HTTP_SEC_FETCH_SITE' => 'same-origin']);

        self::assertSame(302, $client->getResponse()->getStatusCode(), $client->getResponse()->getContent());
    }

    public function testScheduledMessageTooSoonIsRejected(): void
    {
        $client = static::createClient();
        $this->prepareSlackSession($client);

        $crawler = $client->request('POST', '/message/slack', ['message' => ['message' => '', 'notes' => ['toto1' => '', 'toto2' => ''], 'options' => ['scheduled_at' => time() + 60], '_token' => 'csrf-token']], [], ['HTTP_SEC_FETCH_SITE' => 'same-origin']);

        self::assertSame(200, $client->getResponse()->getStatusCode());
        self::assertStringContainsString('You can only schedule a Secret Santa for at least 10 minutes away in the future', $crawler->text());
    }

    private function prepareSlackSession(KernelBrowser $client): void
    {
        $config = new Config('slack', 'toto', null);
        $config->setAvailableUsers(['toto1' => new User('toto1', 'Toto 1', ['image' => '', 'nickname' => '']), 'toto2' => new User('toto2', 'Toto 2', ['image' => '', 'nickname' => ''])]);
        $config->setSelectedUsers(['toto1', 'toto2']);

        $this->prepareSession($client, [
            'santa.slack.token' => new ApplicationToken('token', ['team' => 'team']),
            'santa.slack.admin' => new User('toto1', 'Toto 1'),
            'config' => $config,
        ]);
    }
}
