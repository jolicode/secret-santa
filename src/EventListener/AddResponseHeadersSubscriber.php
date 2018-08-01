<?php

/*
 * This file is part of the Secret Santa project.
 *
 * (c) JoliCode <coucou@jolicode.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace JoliCode\SecretSanta\EventListener;

use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpKernel\Event\FilterResponseEvent;
use Symfony\Component\HttpKernel\KernelEvents;

class AddResponseHeadersSubscriber implements EventSubscriberInterface
{
    public function addHeaders(FilterResponseEvent $event)
    {
        if (!$event->isMasterRequest()) {
            return;
        }

        $event->getResponse()->headers->set('Content-Security-Policy', 'default-src \'self\'; base-uri \'self\';');
        $event->getResponse()->headers->set('X-Content-Type-Options', 'nosniff');
        $event->getResponse()->headers->set('X-XSS-Protection', '1; mode=block');
        $event->getResponse()->headers->set('X-Frame-Options', 'DENY');
    }

    public static function getSubscribedEvents()
    {
        return [
            KernelEvents::RESPONSE => ['addHeaders'],
        ];
    }
}
