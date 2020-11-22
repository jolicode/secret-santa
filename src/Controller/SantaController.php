<?php

/*
 * This file is part of the Secret Santa project.
 *
 * (c) JoliCode <coucou@jolicode.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace JoliCode\SecretSanta\Controller;

use Bugsnag\Client;
use JoliCode\SecretSanta\Application\ApplicationInterface;
use JoliCode\SecretSanta\Exception\MessageDispatchTimeoutException;
use JoliCode\SecretSanta\Exception\MessageSendFailedException;
use JoliCode\SecretSanta\Exception\SecretSantaException;
use JoliCode\SecretSanta\Model\SecretSanta;
use JoliCode\SecretSanta\Model\User;
use JoliCode\SecretSanta\Santa\MessageDispatcher;
use JoliCode\SecretSanta\Santa\Rudolph;
use JoliCode\SecretSanta\Santa\Spoiler;
use JoliCode\SecretSanta\Statistic\StatisticCollector;
use Psr\Log\LoggerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\RouterInterface;
use Twig\Environment;

class SantaController extends AbstractController
{
    private $router;
    private $twig;
    private $logger;
    private $applications;
    private $statisticCollector;
    private $bugsnag;

    /**
     * @param \Iterator<ApplicationInterface> $applications
     */
    public function __construct(RouterInterface $router, Environment $twig, LoggerInterface $logger, iterable $applications,
                                StatisticCollector $statistic, Client $bugsnag)
    {
        $this->router = $router;
        $this->twig = $twig;
        $this->logger = $logger;
        $this->applications = $applications;
        $this->statisticCollector = $statistic;
        $this->bugsnag = $bugsnag;
    }

    public function run(Request $request, string $application): Response
    {
        $application = $this->getApplication($application);

        if (!$application->isAuthenticated()) {
            return new RedirectResponse($this->router->generate($application->getAuthenticationRoute()));
        }

        $this->doReset(null, $request);

        return $this->redirectToRoute('participants', ['application' => $application->getCode()]);
    }

    public function participants(Request $request, string $application): Response
    {
        $application = $this->getApplication($application);

        if (!$application->isAuthenticated()) {
            return new RedirectResponse($this->router->generate($application->getAuthenticationRoute()));
        }

        $session = $request->getSession();
        $availableUsers = $session->get('available-users');

        if (!$availableUsers) {
            $availableUsers = $application->getUsers();

            $session->set('available-users', $availableUsers);
        }

        $selectedUsers = $session->get('selected-users', []);
        $errors = [];

        if ($request->isMethod('POST')) {
            $selectedUsers = $request->request->get('users', []);

            if (\count($selectedUsers) > 1) {
                $session->set('selected-users', $selectedUsers);

                return $this->redirectToRoute('message', ['application' => $application->getCode()]);
            }

            $errors[] = 'At least 2 users should be selected.';
        }

        $content = $this->twig->render('santa/application/participants_' . $application->getCode() . '.html.twig', [
            'application' => $application->getCode(),
            'users' => $availableUsers,
            'groups' => $application->getGroups(),
            'selectedUsers' => $selectedUsers,
            'errors' => $errors,
        ]);

        return new Response($content);
    }

    public function message(Rudolph $rudolph, Request $request, string $application): Response
    {
        $application = $this->getApplication($application);

        if (!$application->isAuthenticated()) {
            return new RedirectResponse($this->router->generate($application->getAuthenticationRoute()));
        }

        $session = $request->getSession();
        $availableUsers = $session->get('available-users', []);
        $selectedUsers = $session->get('selected-users', []);
        $message = $session->get('message');
        $notes = $session->get('notes');

        if ($request->isMethod('POST')) {
            $message = $request->request->get('message');
            $notes = $request->request->get('notes');

            $session->set('message', $message);
            $session->set('notes', $notes);

            $secretSanta = $this->prepareSecretSanta($rudolph, $request, $application);

            $session->set(
                $this->getSecretSantaSessionKey(
                    $secretSanta->getHash()
                ), $secretSanta
            );

            return $this->redirectToRoute('send_messages', ['hash' => $secretSanta->getHash()]);
        }

        $content = $this->twig->render('santa/application/message_' . $application->getCode() . '.html.twig', [
            'application' => $application->getCode(),
            'admin' => $application->getAdmin(),
            'availableUsers' => $availableUsers,
            'selectedUsers' => $selectedUsers,
            'message' => $message,
            'notes' => $notes,
        ]);

        return new Response($content);
    }

    public function sendSampleMessage(Request $request, string $application): Response
    {
        $application = $this->getApplication($application);

        $errors = [];

        if (!$application->isAuthenticated()) {
            $errors['login'] = 'Your session has expired. Please refresh the page.';
        } elseif (!$application->getAdmin()) {
            // An admin is required to use the sample feature
            // Should not happen has the Admin should always be defined
            $errors['no_admin'] = 'You are not allowed to use this feature.';

            $this->bugsnag->notifyError('sample_no_admin', 'Tries to send a sample message without an admin.', function ($report) {
                $report->setSeverity('info');
            });
        }

        if (\count($errors) < 1) {
            $session = $request->getSession();
            $availableUsers = $session->get('available-users', []);
            $selectedUsers = $session->get('selected-users', []);

            $message = $request->request->get('message', '');
            $notes = array_filter($request->request->get('notes', []));

            if ($notes) {
                $receiver = array_rand($notes);
            } else {
                $receiver = $selectedUsers[array_rand($selectedUsers)];
            }

            $secretSanta = new SecretSanta(
                $application->getCode(),
                $application->getOrganization(),
                'sample',
                $availableUsers,
                [],
                $application->getAdmin(),
                str_replace('```', '', $message),
                $notes
            );

            try {
                $application->sendSecretMessage($secretSanta, $application->getAdmin()->getIdentifier(), $receiver, true);

                $this->statisticCollector->incrementSampleCount($secretSanta);
            } catch (MessageSendFailedException $e) {
                $errors['send'] = $e->getMessage();
            }
        }

        return new JsonResponse([
            'success' => empty($errors),
            'errors' => $errors,
        ]);
    }

    public function sendMessages(MessageDispatcher $messageDispatcher, Request $request, string $hash): Response
    {
        $secretSanta = $this->getSecretSantaOrThrow404($request, $hash);
        $application = $this->getApplication($secretSanta->getApplication());

        if (!$request->isXmlHttpRequest()) {
            if ($secretSanta->isDone()) {
                return new RedirectResponse($this->router->generate('finish', [
                    'hash' => $secretSanta->getHash(),
                ]));
            }

            if (!$application->isAuthenticated()) {
                return new RedirectResponse($this->router->generate($application->getAuthenticationRoute()));
            }

            $content = $this->twig->render('santa/send_messages.html.twig', [
                'application' => $application->getCode(),
                'secretSanta' => $secretSanta,
            ]);

            return new Response($content);
        }

        $timeout = false;
        $error = false;

        try {
            $messageDispatcher->dispatchRemainingMessages($secretSanta, $application);
        } catch (MessageDispatchTimeoutException $e) {
            $timeout = true;
        } catch (SecretSantaException $e) {
            $this->logger->error($e->getMessage(), [
                'exception' => $e,
            ]);

            $this->bugsnag->notifyException($e, function ($report) {
                $report->setSeverity('info');
            });

            $secretSanta->addError($e->getMessage());

            $error = true;
        }

        $this->finishSantaIfDone($request, $secretSanta, $application);

        $request->getSession()->set(
            $this->getSecretSantaSessionKey(
                $secretSanta->getHash()
            ), $secretSanta
        );

        return new JsonResponse([
            'count' => \count($secretSanta->getAssociations()) - \count($secretSanta->getRemainingAssociations()),
            'timeout' => $timeout,
            'finished' => $error || $secretSanta->isDone(),
        ]);
    }

    public function finish(Request $request, string $hash): Response
    {
        $secretSanta = $this->getSecretSantaOrThrow404($request, $hash);

        $content = $this->twig->render('santa/finish.html.twig', [
            'secretSanta' => $secretSanta,
        ]);

        return new Response($content);
    }

    public function cancel(Request $request, string $application): Response
    {
        $application = $this->getApplication($application);

        if (!$application->isAuthenticated()) {
            return $this->redirectToRoute('homepage');
        }

        $this->doReset($application, $request);

        return $this->redirectToRoute('homepage');
    }

    public function spoil(Request $request, Spoiler $spoiler): Response
    {
        $code = $request->request->get('code');
        $invalidCode = false;
        $associations = null;

        if ($code) {
            $associations = $spoiler->decode($code);

            if (null === $associations) {
                $invalidCode = true;
            } else {
                $this->statisticCollector->incrementSpoilCount();
            }
        }

        $content = $this->twig->render('santa/spoil.html.twig', [
            'code' => $code,
            'invalidCode' => $invalidCode,
            'associations' => $associations,
        ]);

        return new Response($content);
    }

    private function getApplication(string $code): ApplicationInterface
    {
        foreach ($this->applications as $application) {
            if ($application->getCode() === $code) {
                return $application;
            }
        }

        throw $this->createNotFoundException(sprintf('Unknown application %s.', $code));
    }

    private function getSecretSantaSessionKey(string $hash): string
    {
        return sprintf('secret-santa-%s', $hash);
    }

    private function prepareSecretSanta(Rudolph $rudolph, Request $request, ApplicationInterface $application): SecretSanta
    {
        $session = $request->getSession();
        $availableUsers = $session->get('available-users');
        $selectedUsers = $session->get('selected-users');
        $message = $session->get('message');
        $notes = $session->get('notes');

        $associatedUsers = $rudolph->associateUsers($selectedUsers);
        $hash = md5(serialize($associatedUsers));

        return new SecretSanta(
            $application->getCode(),
            $application->getOrganization(),
            $hash,
            array_filter($availableUsers, function (User $user) use ($selectedUsers) {
                return \in_array($user->getIdentifier(), $selectedUsers, true);
            }),
            $associatedUsers,
            $application->getAdmin(),
            $message,
            $notes
        );
    }

    private function getSecretSantaOrThrow404(Request $request, string $hash): SecretSanta
    {
        $secretSanta = $request->getSession()->get(
            $this->getSecretSantaSessionKey(
                $hash
            )
        );

        if (!$secretSanta) {
            throw $this->createNotFoundException('No secret santa found in session.');
        }

        return $secretSanta;
    }

    private function doReset(?ApplicationInterface $application, Request $request): void
    {
        $session = $request->getSession();
        $session->remove('available-users');
        $session->remove('selected-users');
        $session->remove('message');
        $session->remove('notes');

        if ($application) {
            $application->reset();
        }
    }

    private function finishSantaIfDone(Request $request, SecretSanta $secretSanta, ApplicationInterface $application): void
    {
        if ($secretSanta->isDone()) {
            $this->statisticCollector->incrementUsageCount($secretSanta);
            $this->doReset($application, $request);
        }
    }
}
