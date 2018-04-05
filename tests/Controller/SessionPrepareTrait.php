<?php

/*
 * This file is part of the Slack Secret Santa project.
 *
 * (c) JoliCode <coucou@jolicode.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Joli\SlackSecretSanta\tests\Controller;

use Symfony\Bundle\FrameworkBundle\Client;

trait SessionPrepareTrait
{
    public function prepareSession(Client $client, string $key, $value)
    {
        $session = self::$kernel->getContainer()->get('session');
        $session->start();
        $session->set($key, $value);
        $session->save();
    }
}
