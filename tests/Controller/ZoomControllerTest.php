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

use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

class ZoomControllerTest extends WebTestCase
{
    use SessionPrepareTrait;

    public function test_auth_page_redirects_to_zoom()
    {
        $client = static::createClient();

        $crawler = $client->request('GET', '/auth/zoom');
        $response = $client->getResponse();

        self::assertSame(302, $response->getStatusCode());
        self::assertContains('https://zoom.us/oauth/authorize', $response->getTargetUrl());
    }
}
