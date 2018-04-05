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

class SlackControllerTest extends WebTestCase
{
    use SessionPrepareTrait;

    public function test_auth_page_redirects_to_slack()
    {
        $client = static::createClient();

        $crawler = $client->request('GET', '/auth/slack');
        $response = $client->getResponse();

        self::assertSame(302, $response->getStatusCode());
        self::assertContains('https://slack.com/oauth', $response->getTargetUrl());
    }
}
