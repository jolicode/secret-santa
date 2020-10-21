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
    public function test_homepage_works(): void
    {
        $client = static::createClient();

        $crawler = $client->request('GET', '/');
        $response = $client->getResponse();

        self::assertSame(200, $response->getStatusCode());
        self::assertCount(1, $crawler->filter('html:contains("Merry Christmas!")'));
    }

    public function test_hall_of_fame_works(): void
    {
        $client = static::createClient();

        $crawler = $client->request('GET', '/hall-of-fame');
        $response = $client->getResponse();

        self::assertSame(200, $response->getStatusCode());
        self::assertCount(1, $crawler->filter('img[alt="Monsieur Biz"]'));
    }

    public function test_faq_works(): void
    {
        $client = static::createClient();

        $crawler = $client->request('GET', '/faq');
        $response = $client->getResponse();

        self::assertSame(200, $response->getStatusCode());
        self::assertCount(1, $crawler->filter('h1:contains("Help and Frequently Asked Questions")'));
    }

    public function test_stats_works(): void
    {
        $client = static::createClient();

        $crawler = $client->request('GET', '/stats');
        $response = $client->getResponse();

        self::assertSame(200, $response->getStatusCode());
        self::assertCount(1, $crawler->filter('h1:contains("Secret Santa Statistics")'));
    }

    public function test_terms_works(): void
    {
        $client = static::createClient();

        $crawler = $client->request('GET', '/terms-of-service');
        $response = $client->getResponse();

        self::assertSame(200, $response->getStatusCode());
        self::assertCount(1, $crawler->filter('h1:contains("Secret Santa Terms of Service")'));
    }

    public function test_privacy_policy_works(): void
    {
        $client = static::createClient();

        $crawler = $client->request('GET', '/privacy-policy');
        $response = $client->getResponse();

        self::assertSame(200, $response->getStatusCode());
        self::assertCount(1, $crawler->filter('h1:contains("Privacy Policy")'));
    }

    public function test_sitemap_works(): void
    {
        $client = static::createClient();

        $crawler = $client->request('GET', '/sitemap.xml');
        $response = $client->getResponse();

        self::assertSame(200, $response->getStatusCode());
        self::assertCount(1, $crawler->filter('loc:contains("/faq")'));
    }
}
