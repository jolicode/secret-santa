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

use JoliCode\SecretSanta\Slack\SlackProvider;
use League\OAuth2\Client\Token\AccessToken;
use PHPUnit\Framework\TestCase;

class SlackProviderTest extends TestCase
{
    public function testScopesNotGrantedBySlackAreReported(): void
    {
        $provider = new SlackProvider();

        self::assertSame(
            ['channels:read', 'groups:read', 'im:write', 'team:read'],
            $provider->getMissingScopes(new AccessToken(['access_token' => 'token', 'scope' => 'chat:write,users:read,usergroups:read'])),
        );
        self::assertSame(
            [],
            $provider->getMissingScopes(new AccessToken(['access_token' => 'token', 'scope' => 'channels:read,chat:write,groups:read,im:write,team:read,usergroups:read,users:read,commands'])),
        );
    }
}
