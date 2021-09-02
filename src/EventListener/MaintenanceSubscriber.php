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

use JoliCode\SecretSanta\Controller\SantaController;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\KernelEvents;
use Twig\Environment;

class MaintenanceSubscriber implements EventSubscriberInterface
{
    private Environment $twig;

    public function __construct(Environment $twig)
    {
        $this->twig = $twig;
    }

    public function handleMaintenance(RequestEvent $event): void
    {
        if (!$event->isMainRequest()) {
            return;
        }

        $maintenance = (int) $_SERVER['MAINTENANCE_MODE'];

        if (0 === $maintenance) {
            return;
        }

        $request = $event->getRequest();
        $controller = $request->attributes->get('_controller');

        if (1 === $maintenance && $controller !== SantaController::class . '::run') {
            return;
        }

        $event->setResponse(new Response(
            $this->twig->render('bundles/TwigBundle/Exception/error503.html.twig'),
            503
        ));
    }

    public static function getSubscribedEvents()
    {
        return [
            KernelEvents::REQUEST => ['handleMaintenance'],
        ];
    }
}
