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

class BaseWebTestCase extends WebTestCase
{
    protected static function createClient(array $options = [], array $server = [])
    {
        $server['HTTPS'] = $server['HTTPS'] ?? true;
        return parent::createClient($options, $server);
    }
}
