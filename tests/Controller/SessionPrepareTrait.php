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

    private RequestStack $requestStack;

    public function __construct(RequestStack $requestStack)
    {
        $this->requestStack = $requestStack;
    }
    /**
     * @param mixed $value
     */
    public function prepareSession(KernelBrowser $client, string $key, $value): void
    {
        $session = $this->requestStack->getSession();
        $session->start();
        $session->set($key, $value);
        $session->save();
    }
}
