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

class ContentControllerTest extends BaseWebTestCase
{
    public function testHomepageWorks(): void
    {
        $client = static::createClient();

        $crawler = $client->request('GET', '/');
        $response = $client->getResponse();

        self::assertSame(200, $response->getStatusCode());
        self::assertCount(1, $crawler->filter('html:contains("Merry Christmas!")'));
    }

    public function testHomepageWorksHttp(): void
    {
        $client = static::createClient([], ['HTTPS' => false]);

        $client->request('GET', '/');
        $response = $client->getResponse();

        self::assertSame(301, $response->getStatusCode());
    }

    public function testHallOfFameWorks(): void
    {
        $client = static::createClient();

        $crawler = $client->request('GET', '/hall-of-fame');
        $response = $client->getResponse();

        self::assertSame(200, $response->getStatusCode());
        self::assertCount(1, $crawler->filter('img[alt="Monsieur Biz"]'));
    }

    public function testFaqWorks(): void
    {
        $client = static::createClient();

        $crawler = $client->request('GET', '/faq');
        $response = $client->getResponse();

        self::assertSame(200, $response->getStatusCode());
        self::assertCount(1, $crawler->filter('h1:contains("Help and Frequently Asked Questions")'));
    }

    public function testStatsWorks(): void
    {
        $client = static::createClient();

        $crawler = $client->request('GET', '/stats');
        $response = $client->getResponse();

        self::assertSame(200, $response->getStatusCode());
        self::assertCount(1, $crawler->filter('h1:contains("Secret Santa Statistics")'));
    }

    public function testTermsWorks(): void
    {
        $client = static::createClient();

        $crawler = $client->request('GET', '/terms-of-service');
        $response = $client->getResponse();

        self::assertSame(200, $response->getStatusCode());
        self::assertCount(1, $crawler->filter('h1:contains("Secret Santa Terms of Service")'));
    }

    public function testPrivacyPolicyWorks(): void
    {
        $client = static::createClient();

        $crawler = $client->request('GET', '/privacy-policy');
        $response = $client->getResponse();

        self::assertSame(200, $response->getStatusCode());
        self::assertCount(1, $crawler->filter('h1:contains("Privacy Policy")'));
    }

    public function testSitemapWorks(): void
    {
        $client = static::createClient();

        $crawler = $client->request('GET', '/sitemap.xml');
        $response = $client->getResponse();

        self::assertSame(200, $response->getStatusCode());
        self::assertCount(1, $crawler->filter('loc:contains("/faq")'));
    }
}
