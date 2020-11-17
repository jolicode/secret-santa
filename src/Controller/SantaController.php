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

    public function run(Rudolph $rudolph, Request $request, string $application): Response
    {
        $application = $this->getApplication($application);

        if (!$application->isAuthenticated()) {
            return new RedirectResponse($this->router->generate($application->getAuthenticationRoute()));
        }

        $allUsers = $application->getUsers();

        $selectedUsers = [];
        $message = null;
        $errors = [];

        if ($request->isMethod('POST')) {
            $selectedUsers = $request->request->get('users', []);
            $message = $request->request->get('message');

            $errors = $this->validate($selectedUsers, $message);

            if (\count($errors) < 1) {
                if ($request->request->has('notesRedirect')) {
                    $request->getSession()->set('setup', [
                        'selectedUsers' => $request->request->all('users'),
                        'message' => $request->request->get('message'),
                    ]);

                    return $this->redirectToRoute('notes', [
                        'application' => $application->getCode(),
                    ]);
                }

                $associatedUsers = $rudolph->associateUsers($selectedUsers);
                $hash = md5(serialize($associatedUsers));
                $notes = $request->request->all('notes');

                $secretSanta = new SecretSanta(
                    $application->getCode(),
                    $application->getOrganization(),
                    $hash,
                    array_filter($allUsers, function (User $user) use ($selectedUsers) {
                        return \in_array($user->getIdentifier(), $selectedUsers, true);
                    }),
                    $associatedUsers,
                    $application->getAdmin(),
                    $message,
                    $notes
                );

                $request->getSession()->set(
                    $this->getSecretSantaSessionKey(
                        $secretSanta->getHash()
                    ), $secretSanta
                );

                return new RedirectResponse($this->router->generate('send_messages', ['hash' => $secretSanta->getHash()]));
            }
        }

        $content = $this->twig->render('santa/application/run_' . $application->getCode() . '.html.twig', [
            'application' => $application->getCode(),
            'users' => $allUsers,
            'groups' => $application->getGroups(),
            'admin' => $application->getAdmin(),
            'selectedUsers' => $selectedUsers,
            'message' => $message,
            'errors' => $errors,
        ]);

        return new Response($content);
    }

    public function notes(Request $request, string $application): Response
    {
        $setup = $request->getSession()->get('setup');
        $application = $this->getApplication($application);

        $content = $this->twig->render('santa/application/notes_' . $application->getCode() . '.html.twig', [
            'application' => $application->getCode(),
            'users' => $application->getUsers(),
            'groups' => $application->getGroups(),
            'selectedUsers' => $setup['selectedUsers'],
            'admin' => $application->getAdmin(),
            'message' => $setup['message'],
        ]);

        return new Response($content);
    }

    public function sendSampleMessage(Request $request, string $application): Response
    {
        $application = $this->getApplication($application);

        $errors = [];
        $message = '';

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
            $message = $request->request->get('message', '');

            $errors = $this->validate([], $message, true);
        }

        if (\count($errors) < 1) {
            $secretSanta = new SecretSanta(
                $application->getCode(),
                $application->getOrganization(),
                'sample',
                [$application->getAdmin()->getIdentifier() => $application->getAdmin()],
                [],
                $application->getAdmin(),
                str_replace('```', '', $message)
            );

            try {
                $application->sendSecretMessage($secretSanta, $application->getAdmin()->getIdentifier(), $application->getAdmin()->getIdentifier(), true);

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

        $this->finishSantaIfDone($secretSanta, $application);

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

    public function cancel(string $application): Response
    {
        $application = $this->getApplication($application);

        if (!$application->isAuthenticated()) {
            return $this->redirectToRoute('homepage');
        }

        $application->reset();

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

    /**
     * @param string[] $selectedUsers
     *
     * @return array<string, string[]>
     */
    private function validate(array $selectedUsers, string $message, bool $isSample = false): array
    {
        $errors = [];

        if (!$isSample && \count($selectedUsers) < 2) {
            $errors['users'][] = 'At least 2 users should be selected.';
        }

        return $errors;
    }

    private function finishSantaIfDone(SecretSanta $secretSanta, ApplicationInterface $application): void
    {
        if ($secretSanta->isDone()) {
            $this->statisticCollector->incrementUsageCount($secretSanta);
            $application->reset();
        }
    }
}
