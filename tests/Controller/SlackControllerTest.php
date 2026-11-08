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

use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Component\HttpFoundation\RedirectResponse;

class SlackControllerTest extends BaseWebTestCase
{
    public function testAuthPageRedirectsToSlack(): void
    {
        $client = static::createClient();

        $crawler = $client->request('GET', '/auth/slack');
        $response = $client->getResponse();

        self::assertInstanceOf(RedirectResponse::class, $response);
        self::assertSame(302, $response->getStatusCode());
        self::assertStringContainsString('https://slack.com/oauth', $response->getTargetUrl());
    }

    #[DataProvider('provideChannelPages')]
    public function testChannelPagesRequireAuthentication(string $path): void
    {
        $client = static::createClient();

        $client->request('GET', $path);
        $response = $client->getResponse();

        self::assertInstanceOf(RedirectResponse::class, $response);
        self::assertSame('/auth/slack', $response->getTargetUrl());
    }

    /**
     * @return iterable<array{string}>
     */
    public static function provideChannelPages(): iterable
    {
        yield ['/auth/slack/channels'];
        yield ['/auth/slack/channels/invitation'];
    }
}
