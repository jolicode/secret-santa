<?php

/*
 * This file is part of the Secret Santa project.
 *
 * (c) JoliCode <coucou@jolicode.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace JoliCode\SecretSanta\Tests\Slack;

use JoliCode\SecretSanta\Model\Config;
use JoliCode\SecretSanta\Model\Group;
use JoliCode\SecretSanta\Model\User;
use JoliCode\SecretSanta\Slack\ClientFactory;
use JoliCode\SecretSanta\Slack\UserExtractor;
use JoliCode\Slack\Api\Client;
use JoliCode\Slack\Api\Model\ObjsConversation;
use JoliCode\Slack\Api\Model\ObjsTeam;
use JoliCode\Slack\Api\Model\ObjsUser;
use JoliCode\Slack\Api\Model\ObjsUserProfile;
use JoliCode\Slack\Api\Model\TeamInfoGetResponse200;
use JoliCode\Slack\Api\Model\UsersConversationsGetResponse200;
use JoliCode\Slack\Api\Model\UsersInfoGetResponse200;
use JoliCode\Slack\Api\Model\UsersListGetResponse200;
use PHPUnit\Framework\TestCase;

class UserExtractorTest extends TestCase
{
    public function testExternalUsersOfTheChannelsAreLoadedOnceTheWorkspaceIs(): void
    {
        $test = $this;

        // No PHPUnit mocks: phpstan-phpunit does not know PHPUnit 12 mock builders yet
        $client = new class($test) extends Client {
            public int $usersInfoCalls = 0;

            public function __construct(private TestCase $test)
            {
            }

            /** @param array<string, mixed> $queryParameters */
            public function usersList(array $queryParameters = [], string $fetch = self::FETCH_OBJECT): UsersListGetResponse200
            {
                return new UsersListGetResponse200()->setOk(true)->setMembers([
                    UserExtractorTest::slackUser('U_LOCAL', 'Local'),
                    UserExtractorTest::slackUser('U_BOT', 'Bot')->setIsBot(true),
                ]);
            }

            /** @param array<string, mixed> $queryParameters */
            public function teamInfo(array $queryParameters = [], string $fetch = self::FETCH_OBJECT): TeamInfoGetResponse200
            {
                $this->test::assertSame(['team' => 'T_EXTERNAL'], $queryParameters);

                return new TeamInfoGetResponse200()->setOk(true)->setTeam(new ObjsTeam()->setName('Other Workspace'));
            }

            /** @param array<string, mixed> $queryParameters */
            public function usersInfo(array $queryParameters = [], string $fetch = self::FETCH_OBJECT): UsersInfoGetResponse200
            {
                ++$this->usersInfoCalls;
                $this->test::assertSame(['user' => 'U_EXTERNAL'], $queryParameters);

                return new UsersInfoGetResponse200()->setOk(true)->setUser(UserExtractorTest::slackUser('U_EXTERNAL', 'External'));
            }
        };

        $clientFactory = new class($client) extends ClientFactory {
            public function __construct(private Client $client)
            {
            }

            public function getClientForToken(string $token): Client
            {
                return $this->client;
            }
        };

        $sharedChannel = new Group('C_SHARED', '#santa');
        $sharedChannel->addUser('U_LOCAL');
        $sharedChannel->addUser('U_EXTERNAL');
        // A local bot in the channel is not an external member, users.info must not be called for it
        $sharedChannel->addUser('U_BOT');

        $config = new Config('slack', 'org', null);

        $userExtractor = new UserExtractor($clientFactory);

        $users = $userExtractor->loadNextBatchOfUsers('token', $config, ['C_SHARED' => $sharedChannel]);
        self::assertSame(['U_LOCAL'], array_keys($users));
        self::assertNull($users['U_LOCAL']->getExtra()['team']);
        self::assertFalse($config->areUsersLoaded(), 'The external users remain to be loaded');
        self::assertSame(0, $client->usersInfoCalls);

        $users = $userExtractor->loadNextBatchOfUsers('token', $config, ['C_SHARED' => $sharedChannel]);
        self::assertSame(['U_EXTERNAL'], array_keys($users));
        self::assertSame('External', $users['U_EXTERNAL']->getName());
        self::assertSame('Other Workspace', $users['U_EXTERNAL']->getExtra()['team']);
        self::assertTrue($config->areUsersLoaded());
        self::assertSame(1, $client->usersInfoCalls);
    }

    public function testExternalUsersAreLoadedByBatchesOfTwentyFive(): void
    {
        $client = new class extends Client {
            public int $teamInfoCalls = 0;

            public function __construct()
            {
            }

            /** @param array<string, mixed> $queryParameters */
            public function usersList(array $queryParameters = [], string $fetch = self::FETCH_OBJECT): UsersListGetResponse200
            {
                return new UsersListGetResponse200()->setOk(true)->setMembers([]);
            }

            /** @param array<string, mixed> $queryParameters */
            public function teamInfo(array $queryParameters = [], string $fetch = self::FETCH_OBJECT): TeamInfoGetResponse200
            {
                ++$this->teamInfoCalls;

                return new TeamInfoGetResponse200()->setOk(true)->setTeam(new ObjsTeam()->setName('Other Workspace'));
            }

            /** @param array<string, mixed> $queryParameters */
            public function usersInfo(array $queryParameters = [], string $fetch = self::FETCH_OBJECT): UsersInfoGetResponse200
            {
                return new UsersInfoGetResponse200()->setOk(true)->setUser(UserExtractorTest::slackUser($queryParameters['user'], 'External'));
            }
        };

        $clientFactory = new class($client) extends ClientFactory {
            public function __construct(private Client $client)
            {
            }

            public function getClientForToken(string $token): Client
            {
                return $this->client;
            }
        };

        $sharedChannel = new Group('C_SHARED', '#santa');
        foreach (range(1, 26) as $i) {
            $sharedChannel->addUser('U_EXT_' . $i);
        }

        $config = new Config('slack', 'org', null);
        $userExtractor = new UserExtractor($clientFactory);

        // PHPStan narrows repeated identical calls after an assertion, hence one variable per batch
        $localUsers = $userExtractor->loadNextBatchOfUsers('token', $config, ['C_SHARED' => $sharedChannel]);
        self::assertSame([], $localUsers);

        $firstBatch = $userExtractor->loadNextBatchOfUsers('token', $config, ['C_SHARED' => $sharedChannel]);
        self::assertCount(25, $firstBatch);
        self::assertFalse($config->areUsersLoaded());

        $lastBatch = $userExtractor->loadNextBatchOfUsers('token', $config, ['C_SHARED' => $sharedChannel]);
        self::assertSame(['U_EXT_26'], array_keys($lastBatch));
        self::assertTrue($config->areUsersLoaded());
        self::assertSame(1, $client->teamInfoCalls, 'The workspace name is cached across batches');

        $nothingLeft = $userExtractor->loadNextBatchOfUsers('token', $config, ['C_SHARED' => $sharedChannel]);
        self::assertSame([], $nothingLeft);
    }

    public function testTheAdminChannelsAreListedWithSharingAndBotMembership(): void
    {
        $client = new class extends Client {
            public function __construct()
            {
            }

            /** @param array<string, mixed> $queryParameters */
            public function usersConversations(array $queryParameters = [], string $fetch = self::FETCH_OBJECT): UsersConversationsGetResponse200
            {
                $local = new ObjsConversation()->setId('C_LOCAL')->setName('local')->setIsExtShared(false);
                $alpha = new ObjsConversation()->setId('C_ALPHA')->setName('Alpha')->setIsExtShared(true);
                $zebra = new ObjsConversation()->setId('C_ZEBRA')->setName('zebra')->setIsExtShared(true);

                // The admin is in the three channels, the bot only in two of them
                return new UsersConversationsGetResponse200()->setOk(true)->setChannels(
                    'U_ADMIN' === ($queryParameters['user'] ?? null) ? [$local, $zebra, $alpha] : [$local, $alpha],
                );
            }
        };

        $clientFactory = new class($client) extends ClientFactory {
            public function __construct(private Client $client)
            {
            }

            public function getClientForToken(string $token): Client
            {
                return $this->client;
            }
        };

        $channels = new UserExtractor($clientFactory)->listChannels('token', new User('U_ADMIN', 'Admin'));

        self::assertSame(['C_ALPHA', 'C_LOCAL', 'C_ZEBRA'], array_keys($channels), 'Sorted by name');
        self::assertFalse($channels['C_LOCAL']->isShared);
        self::assertFalse($channels['C_LOCAL']->needsInvitation());
        self::assertFalse($channels['C_ALPHA']->needsInvitation(), 'The bot is already in the shared channel');
        self::assertTrue($channels['C_ZEBRA']->needsInvitation());
    }

    public static function slackUser(string $id, string $name): ObjsUser
    {
        return new ObjsUser()
            ->setId($id)
            ->setTeamId('T_' . strtoupper($name))
            ->setName(strtolower($name))
            ->setIsBot(false)
            ->setDeleted(false)
            ->setProfile(new ObjsUserProfile()->setRealName($name)->setImage192('https://example.com/' . $id . '.png'))
        ;
    }
}
