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

use Symfony\Component\HttpFoundation\RedirectResponse;

class DiscordControllerTest extends BaseWebTestCase
{
    public function testAuthPageRedirectsToDiscord(): void
    {
        $client = static::createClient();

        $crawler = $client->request('GET', '/auth/discord');
        $response = $client->getResponse();

        self::assertInstanceOf(RedirectResponse::class, $response);
        self::assertSame(302, (int) $response->getStatusCode());
        self::assertStringContainsString('https://discord.com/api/', $response->getTargetUrl());
    }
}
