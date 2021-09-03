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
use Symfony\Component\HttpFoundation\RequestStack;

trait SessionPrepareTrait
{
    /**
     * @param mixed $value
     */
    public function prepareSession(KernelBrowser $client, string $key, $value): void
    {
        $requestStack = self::$kernel->getContainer()->get(RequestStack::class);
        $session = $requestStack->getSession();
        $session->start();
        $session->set($key, $value);
        $session->save();
    }
}
