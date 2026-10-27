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

class AuthenticationErrorTest extends BaseWebTestCase
{
    #[DataProvider('provideApplications')]
    public function testAuthenticationFailureShowsDedicatedPage(string $path, string $applicationName): void
    {
        $client = static::createClient();

        // A code with a state that does not match the session one triggers an AuthenticationException
        $crawler = $client->request('GET', $path . '?code=foo&state=bar');
        $response = $client->getResponse();

        self::assertSame(401, $response->getStatusCode());
        self::assertSame(\sprintf('We could not sign you in with %s', $applicationName), $crawler->filter('h2')->text());
        self::assertStringContainsString('Your sign-in session has expired, or the link was opened in another browser.', $crawler->filter('.error-code')->text());
        self::assertSame($path, $crawler->selectLink('Try again')->attr('href'));
        self::assertCount(1, $crawler->selectLink('Go back to home page'));
    }

    /**
     * @return iterable<array{string, string}>
     */
    public static function provideApplications(): iterable
    {
        yield 'discord' => ['/auth/discord', 'Discord'];
        yield 'slack' => ['/auth/slack', 'Slack'];
        yield 'webex' => ['/auth/webex', 'Webex'];
    }
}
