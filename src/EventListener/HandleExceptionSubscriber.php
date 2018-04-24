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

use JoliCode\SecretSanta\Exception\AuthenticationException;
use JoliCode\SecretSanta\Exception\UserExtractionFailedException;
use Psr\Log\LoggerInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Event\GetResponseForExceptionEvent;
use Symfony\Component\HttpKernel\KernelEvents;

class HandleExceptionSubscriber implements EventSubscriberInterface
{
    private $logger;
    private $twig;

    public function __construct(LoggerInterface $logger, \Twig_Environment $twig)
    {
        $this->logger = $logger;
        $this->twig = $twig;
    }

    public function handleException(GetResponseForExceptionEvent $event)
    {
        $exception = $event->getException();
        $statusCode = null;

        if ($exception instanceof AuthenticationException) {
            $this->logger->error(sprintf('Authentication error: %s', $exception->getMessage()), [
                'exception' => $exception,
            ]);

            $statusCode = 401;
        } elseif ($exception instanceof UserExtractionFailedException) {
            $this->logger->error('Could not retrieve users', [
                'exception' => $exception,
            ]);

            $statusCode = 500;
        }

        if (!$statusCode) {
            return;
        }

        $response = new Response($this->twig->render('error.html.twig', [
            'exception' => $exception,
        ]), $statusCode);

        $event->setResponse($response);
        $event->stopPropagation();
    }

    public static function getSubscribedEvents()
    {
        return [
            KernelEvents::EXCEPTION => ['handleException', 255],
        ];
    }
}
