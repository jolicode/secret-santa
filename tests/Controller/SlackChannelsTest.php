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
use JoliCode\SecretSanta\Model\Group;
use JoliCode\SecretSanta\Model\SecretSanta;
use JoliCode\SecretSanta\Model\User;
use JoliCode\SecretSanta\Slack\MessageSender;
use JoliCode\SecretSanta\Tests\Fake\FakeSlackApi;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;

/**
 * Restricting the participants to the members of some channels, including people from another
 * workspace through a Slack Connect channel.
 */
class SlackChannelsTest extends BaseWebTestCase
{
    use SessionPrepareTrait;

    public function testOnlyTheMembersOfTheChosenChannelsAreParticipants(): void
    {
        $client = static::createClient();
        $this->logInAsAdmin($client);

        $crawler = $client->request('GET', '/auth/slack/channels');
        self::assertSame(200, $client->getResponse()->getStatusCode());
        self::assertStringContainsString('Channels', $crawler->filter('.breadcrumb')->text(), 'The step shows in the breadcrumb');
        self::assertCount(3, $crawler->filter('input[name="channels[]"]'), 'Every channel is listed');
        self::assertStringContainsString('shared with other workspaces', $crawler->filter('label:contains("#santa")')->text());
        self::assertStringNotContainsString('shared', $crawler->filter('label:contains("#local")')->text());

        $client->request('POST', '/auth/slack/channels', ['scope' => 'channels', 'channels' => ['C_LOCAL', 'C_SANTA']]);
        self::assertResponseRedirects('/run/slack', null, 'The bot is in the shared channel, no invitation needed');
        $client->followRedirect();
        self::assertResponseRedirects('/load-users/slack');

        $this->loadUsers($client);

        $crawler = $client->request('GET', '/participants/slack');
        self::assertSame(200, $client->getResponse()->getStatusCode());
        self::assertSame(['id_U1', 'id_U2', 'id_U3', 'id_UEXT'], $crawler->filter('.user-item input')->extract(['id']), 'Only the members of the chosen channels, external one included');
        self::assertStringContainsString('Frank (frank) · Other Workspace', $crawler->filter('label[for="id_UEXT"]')->text(), 'The external user shows with their workspace');
        self::assertStringNotContainsString('·', $crawler->filter('label[for="id_U1"]')->text(), 'Local users show no workspace');
        self::assertSame('U1|U2|UEXT', $crawler->filter('.group-button:contains("#santa")')->attr('data-user-ids'), 'A chosen channel is a group');
        self::assertSame('U1|U3', $crawler->filter('.group-button:contains("#local")')->attr('data-user-ids'));
        self::assertSame('U2|U3', $crawler->filter('.group-button:contains("Devs")')->attr('data-user-ids'), 'The user groups keep their members of the chosen channels');
    }

    public function testASharedChannelWithoutTheBotAsksForAnInvitation(): void
    {
        $client = static::createClient();
        $this->logInAsAdmin($client);

        $client->request('POST', '/auth/slack/channels', ['scope' => 'channels', 'channels' => ['C_OTHER']]);
        self::assertResponseRedirects('/auth/slack/channels/invitation');

        $crawler = $client->followRedirect();
        self::assertSame(200, $client->getResponse()->getStatusCode());
        self::assertSame(['#other'], $crawler->filter('.user-item span')->extract(['_text']), 'Only the channels missing the bot are listed');
        self::assertCount(1, $crawler->filter('a[href="/auth/slack/channels/invitation"]'), 'The page can be refreshed');

        $crawler = $client->request('GET', '/auth/slack/channels');
        self::assertCount(1, $crawler->filter('input[value="C_OTHER"][checked]'), 'The choice is kept to be changed');
    }

    public function testTheInvitationStepIsSkippedOnceTheBotIsInTheChannels(): void
    {
        $client = static::createClient();
        $this->logInAsAdmin($client, ['santa.slack.channels' => ['C_SANTA' => new Group('C_SANTA', '#santa')]]);

        $client->request('GET', '/auth/slack/channels/invitation');
        self::assertResponseRedirects('/run/slack');
    }

    public function testEveryoneInTheWorkspaceForgetsTheChannels(): void
    {
        $client = static::createClient();
        $this->logInAsAdmin($client, ['santa.slack.channels' => ['C_SANTA' => new Group('C_SANTA', '#santa')]]);

        $client->request('POST', '/auth/slack/channels', ['scope' => 'workspace']);
        self::assertResponseRedirects('/run/slack');
        $client->followRedirect();

        $this->loadUsers($client);

        $crawler = $client->request('GET', '/participants/slack');
        self::assertSame(['id_U1', 'id_U2', 'id_U3', 'id_U4', 'id_U5'], $crawler->filter('.user-item input')->extract(['id']), 'The whole workspace, no external user');
        self::assertCount(0, $crawler->filter('.group-button:contains("#santa")'));
    }

    public function testNoChannelSelectedShowsAnError(): void
    {
        $client = static::createClient();
        $this->logInAsAdmin($client);

        $crawler = $client->request('POST', '/auth/slack/channels', ['scope' => 'channels']);
        self::assertSame(200, $client->getResponse()->getStatusCode());
        self::assertStringContainsString('Select at least one channel', $crawler->filter('.error')->text());
    }

    public function testExternalUsersAreMessagedThroughAnOpenedDirectMessage(): void
    {
        static::bootKernel();
        $api = static::getContainer()->get(FakeSlackApi::class);

        $config = new Config('slack', 'JoliCode', null);
        $config->setAvailableUsers([
            'U1' => new User('U1', 'Alice', ['nickname' => 'alice', 'team' => null]),
            'UEXT' => new User('UEXT', 'Frank', ['nickname' => 'frank', 'team' => 'Other Workspace']),
        ]);
        $secretSanta = new SecretSanta('hash', ['U1' => 'UEXT', 'UEXT' => 'U1'], $config);

        $sender = static::getContainer()->get(MessageSender::class);
        $sender->sendSecretMessage($secretSanta, 'U1', 'UEXT', 'xoxb-fake', false);
        $sender->sendSecretMessage($secretSanta, 'UEXT', 'U1', 'xoxb-fake', false);

        self::assertSame(['U1', 'DUEXT'], array_column($api->postedMessages(), 'to'), 'The local user is messaged directly, the external one through the opened DM');
        self::assertCount(1, array_filter($api->requests, static fn (array $request) => '/api/conversations.open' === $request['path']));
    }

    /**
     * @param array<string, mixed> $attributes
     */
    private function logInAsAdmin(KernelBrowser $client, array $attributes = []): void
    {
        $this->prepareSession($client, $attributes + [
            'santa.slack.token' => new ApplicationToken('xoxb-fake', ['team' => 'JoliCode']),
            'santa.slack.admin' => new User('U1', 'Alice', ['nickname' => 'alice', 'image' => '', 'restricted' => false]),
        ]);
    }

    /**
     * Runs the paginated user loading the way the browser does it, until the application says it is done.
     */
    private function loadUsers(KernelBrowser $client): void
    {
        for ($page = 0; $page < 10; ++$page) {
            $client->xmlHttpRequest('POST', '/load-users/slack');
            $response = json_decode((string) $client->getResponse()->getContent(), true);
            self::assertFalse($response['error'], 'Loading users failed');

            if ($response['finished']) {
                return;
            }
        }

        self::fail('Users never finished loading.');
    }
}
