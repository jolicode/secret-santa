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
use JoliCode\SecretSanta\Slack\ClientFactory;
use JoliCode\SecretSanta\Slack\UserExtractor;
use JoliCode\Slack\Api\Client;
use JoliCode\Slack\Api\Model\ObjsUser;
use JoliCode\Slack\Api\Model\ObjsUserProfile;
use JoliCode\Slack\Api\Model\UsersInfoGetResponse200;
use JoliCode\Slack\Api\Model\UsersListGetResponse200;
use PHPUnit\Framework\TestCase;

class UserExtractorTest extends TestCase
{
    public function testExternalUsersFromSharedChannelsAreLoadedOnLastPage(): void
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
                return (new UsersListGetResponse200())->setOk(true)->setMembers([UserExtractorTest::slackUser('U_LOCAL', 'Local')]);
            }

            /** @param array<string, mixed> $queryParameters */
            public function usersInfo(array $queryParameters = [], string $fetch = self::FETCH_OBJECT): UsersInfoGetResponse200
            {
                ++$this->usersInfoCalls;
                $this->test::assertSame(['user' => 'U_EXTERNAL'], $queryParameters);

                return (new UsersInfoGetResponse200())->setOk(true)->setUser(UserExtractorTest::slackUser('U_EXTERNAL', 'External'));
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

        $config = new Config('slack', 'org', null);
        $config->setGroups([$sharedChannel]);

        $users = (new UserExtractor($clientFactory))->loadNextBatchOfUsers('token', $config);

        self::assertSame(['U_LOCAL', 'U_EXTERNAL'], array_keys($users));
        self::assertSame('External', $users['U_EXTERNAL']->getName());
        self::assertTrue($config->areUsersLoaded());
        self::assertSame(1, $client->usersInfoCalls);
    }

    public static function slackUser(string $id, string $name): ObjsUser
    {
        return (new ObjsUser())
            ->setId($id)
            ->setName(strtolower($name))
            ->setIsBot(false)
            ->setDeleted(false)
            ->setProfile((new ObjsUserProfile())->setRealName($name)->setImage192('https://example.com/' . $id . '.png'))
        ;
    }
}
