<?php

/*
 * This file is part of the Slack Secret Santa project.
 *
 * (c) JoliCode <coucou@jolicode.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Joli\SlackSecretSanta\Tests\Controller;

use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

class ContentControllerTest extends WebTestCase
{
    public function test_homepage_works()
    {
        $client = static::createClient();

        $crawler = $client->request('GET', '/');
        $response = $client->getResponse();

        self::assertSame(200, $response->getStatusCode());
        self::assertCount(1, $crawler->filter('html:contains("Merry Christmas!")'));
    }

    public function test_hall_of_fame_works()
    {
        $client = static::createClient();

        $crawler = $client->request('GET', '/hall-of-fame');
        $response = $client->getResponse();

        self::assertSame(200, $response->getStatusCode());
        self::assertCount(1, $crawler->filter('img[alt="Monsieur Biz"]'));
    }
}
