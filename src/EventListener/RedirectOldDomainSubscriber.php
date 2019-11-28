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
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\KernelEvents;

class RedirectOldDomainSubscriber implements EventSubscriberInterface
{
    public function redirectOldDomain(RequestEvent $event)
    {
        if (!$event->isMasterRequest()) {
            return;
        }

        $request = $event->getRequest();

        if (false === strpos('slack-secret-santa.herokuapp.com', $request->getHost())) {
            return;
        }

        $event->setResponse(new RedirectResponse('https://secret-santa.team' . $request->getRequestUri(), 301));
    }

    public static function getSubscribedEvents()
    {
        return [
            KernelEvents::REQUEST => ['redirectOldDomain', 90000000000],
        ];
    }
}
