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

use Symfony\Bundle\FrameworkBundle\KernelBrowser;

trait SessionPrepareTrait
{
    /**
     * @param mixed $value
     */
    public function prepareSession(KernelBrowser $client, string $key, $value): void
    {
        $session = self::$kernel->getContainer()->get('session');
        $session->start();
        $session->set($key, $value);
        $session->save();
    }
}
