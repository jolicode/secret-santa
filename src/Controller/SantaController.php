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
use JoliCode\SecretSanta\Form\MessageType;
use JoliCode\SecretSanta\Form\ParticipantType;
use JoliCode\SecretSanta\Model\Config;
use JoliCode\SecretSanta\Model\SecretSanta;
use JoliCode\SecretSanta\Santa\MessageDispatcher;
use JoliCode\SecretSanta\Santa\Rudolph;
use JoliCode\SecretSanta\Santa\Spoiler;
use JoliCode\SecretSanta\Statistic\StatisticCollector;
use Psr\Log\LoggerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Form\FormError;
use Symfony\Component\Form\FormFactoryInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Routing\RouterInterface;
use Twig\Environment;

class SantaController extends AbstractController
{
    /**
     * @param \Iterator<ApplicationInterface> $applications
     */
    public function __construct(
        private RouterInterface $router,
        private Environment $twig,
        private LoggerInterface $logger,
        private iterable $applications,
        private StatisticCollector $statisticCollector,
        private Client $bugsnag,
    ) {
    }

    #[Route('/run/{application}', name: 'run', methods: ['GET', 'POST'])]
    public function run(Request $request, string $application): Response
    {
        $application = $this->getApplication($application);

        if (!$application->isAuthenticated()) {
            return new RedirectResponse($this->router->generate($application->getAuthenticationRoute()));
        }

        $this->doReset(null, $request);

        return $this->redirectToRoute('participants', ['application' => $application->getCode()]);
    }

    #[Route('/participants/{application}', name: 'participants', methods: ['GET', 'POST'])]
    public function participants(Request $request, string $application): Response
    {
        $application = $this->getApplication($application);

        if (!$application->isAuthenticated()) {
            return new RedirectResponse($this->router->generate($application->getAuthenticationRoute()));
        }

        $session = $request->getSession();

        $config = $session->get('config');

        if (!$config) {
            $config = new Config($application->getUsers());
            $session->set('config', $config);
        }

        $availableUsers = $config->getAvailableUsers();

        $form = $this->createForm(ParticipantType::class, $config, [
            'available-users' => $availableUsers,
        ]);

        $form->handleRequest($request);

        if ($form->isSubmitted()) {
            $session->set('config', $config);
            if ($form->isValid()) {
                return $this->redirectToRoute('message', ['application' => $application->getCode()]);
            }
        }

        $content = $this->twig->render('santa/application/participants_' . $application->getCode() . '.html.twig', ['application' => $application->getCode(),
            'users' => $availableUsers,
            'groups' => $application->getGroups(),
            'form' => $form->createView(),
        ]);

        return new Response($content);
    }

    #[Route('/message/{application}', name: 'message', methods: ['GET', 'POST'])]
    public function message(Rudolph $rudolph, FormFactoryInterface $formFactory, Request $request, Spoiler $spoiler, string $application): Response
    {
        $application = $this->getApplication($application);

        if (!$application->isAuthenticated()) {
            return new RedirectResponse($this->router->generate($application->getAuthenticationRoute()));
        }

        $errors = [];

        $session = $request->getSession();

        /** @var Config $config * */
        $config = $session->get('config');

        // We remove notes from users that aren't selected anymore, and create empty ones for those who are
        // and don't have any yet.
        $selectedUsersAsArray = $config->getSelectedUsers();
        $notes = array_filter($config->getNotes(), function ($userIdentifier) use ($selectedUsersAsArray) {
            return \in_array($userIdentifier, $selectedUsersAsArray, true);
        }, \ARRAY_FILTER_USE_KEY);
        foreach ($config->getSelectedUsers() as $user) {
            $notes[$user] ??= '';
        }

        $config->setNotes($notes);

        $builder = $formFactory->createBuilder(MessageType::class, $config, [
            'selected-users' => $config->getSelectedUsers(),
        ]);

        $application->configureMessageForm($builder);

        $form = $builder->getForm();

        $form->handleRequest($request);

        if ($form->isSubmitted()) {
            $session->set('config', $config);

            if ($form->isValid()) {
                $secretSanta = $this->prepareSecretSanta($rudolph, $request, $application);
                $session->set(
                    $this->getSecretSantaSessionKey(
                        $secretSanta->getHash()
                    ),
                    $secretSanta
                );

                // Send a summary to the santa admin
                if ($secretSanta->getAdmin()) {
                    $code = $spoiler->encode($secretSanta);
                    $spoilUrl = $this->generateUrl('spoil', [], UrlGeneratorInterface::ABSOLUTE_URL);

                    $application->sendAdminMessage($secretSanta, $code, $spoilUrl);
                }

                return $this->redirectToRoute('send_messages', ['hash' => $secretSanta->getHash()]);
            }

            $errors = array_map(function (FormError $error) {
                return $error->getMessage();
            }, iterator_to_array($form->getErrors(true, false))) ?? [];

            if ($errors) {
                $errors = array_unique($errors);
            }
        }

        $content = $this->twig->render('santa/application/message_' . $application->getCode() . '.html.twig', [
            'application' => $application->getCode(),
            'admin' => $application->getAdmin(),
            'config' => $config,
            'errors' => $errors,
            'form' => $form->createView(),
        ]);

        return new Response($content);
    }

    #[Route('/sample-message/{application}', name: 'send_sample_message', methods: ['GET', 'POST'])]
    public function sendSampleMessage(Request $request, FormFactoryInterface $formFactory, string $application): Response
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

        $session = $request->getSession();

        /** @var Config $config * */
        $config = $session->get('config');

        $builder = $formFactory->createBuilder(MessageType::class, $config, [
            'selected-users' => $config->getSelectedUsers(),
        ]);

        $application->configureMessageForm($builder);

        $form = $builder->getForm();

        $form->handleRequest($request);

        if ($form->isSubmitted()) {
            $notes = $config->getNotes();

            $candidates = array_filter($notes ? array_keys($notes) : $config->getSelectedUsers(), function ($id) use ($application) {
                return $application->getAdmin()->getIdentifier() !== $id;
            });

            $receiver = $candidates ? $candidates[array_rand($candidates)] : $application->getAdmin()->getIdentifier();

            $formErrors = array_map(function (FormError $error) {
                return $error->getMessage();
            }, iterator_to_array($form->getErrors(true, false))) ?? [];

            if ($formErrors) {
                $formErrors = array_unique($formErrors);
            }

            $errors = array_merge($errors, $formErrors);

            if ($form->isValid()) {
                $secretSanta = new SecretSanta(
                    $application->getCode(),
                    $application->getOrganization(),
                    'sample',
                    [],
                    $application->getAdmin(),
                    $config
                );

                try {
                    $application->sendSecretMessage($secretSanta, $application->getAdmin()->getIdentifier(), $receiver, true);

                    $this->statisticCollector->incrementSampleCount($secretSanta);
                } catch (MessageSendFailedException $e) {
                    $errors['send'] = $e->getMessage();
                }
            }
        }

        return new JsonResponse([
            'success' => empty($errors),
            'errors' => $errors,
        ]);
    }

    #[Route('/send-messages/{hash}', name: 'send_messages', methods: ['GET', 'POST'])]
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
            ),
            $secretSanta
        );

        return new JsonResponse([
            'count' => \count($secretSanta->getAssociations()) - \count($secretSanta->getRemainingAssociations()),
            'timeout' => $timeout,
            'finished' => $error || $secretSanta->isDone(),
        ]);
    }

    #[Route('/finish/{hash}', name: 'finish', methods: ['GET'])]
    public function finish(Request $request, string $hash): Response
    {
        $secretSanta = $this->getSecretSantaOrThrow404($request, $hash);

        $content = $this->twig->render('santa/finish.html.twig', [
            'secretSanta' => $secretSanta,
        ]);

        return new Response($content);
    }

    #[Route('/cancel/{application}', name: 'cancel', methods: ['GET'])]
    public function cancel(Request $request, string $application): Response
    {
        $application = $this->getApplication($application);

        if (!$application->isAuthenticated()) {
            return $this->redirectToRoute('homepage');
        }

        $this->doReset($application, $request);

        return $this->redirectToRoute('homepage');
    }

    #[Route('/spoil', name: 'spoil', methods: ['GET', 'POST'])]
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

        /** @var Config $config * */
        $config = $session->get('config');

        $selectedUsersAsArray = $config->getSelectedUsers();

        $associatedUsers = $rudolph->associateUsers($selectedUsersAsArray);

        $hash = md5(serialize($associatedUsers));

        return new SecretSanta(
            $application->getCode(),
            $application->getOrganization(),
            $hash,
            $associatedUsers,
            $application->getAdmin(),
            $config,
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
        $session->remove('config');

        $application?->reset();
    }

    private function finishSantaIfDone(Request $request, SecretSanta $secretSanta, ApplicationInterface $application): void
    {
        if ($secretSanta->isDone()) {
            $this->statisticCollector->incrementUsageCount($secretSanta);
            $this->doReset($application, $request);
        }
    }
}
