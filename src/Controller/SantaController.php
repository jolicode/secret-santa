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
use JoliCode\SecretSanta\Exception\RudolphException;
use JoliCode\SecretSanta\Exception\UserExtractionFailedException;
use JoliCode\SecretSanta\Form\ExclusionsType;
use JoliCode\SecretSanta\Form\MessageType;
use JoliCode\SecretSanta\Model\Config;
use JoliCode\SecretSanta\Model\SecretSanta;
use JoliCode\SecretSanta\Santa\MessageDispatcher;
use JoliCode\SecretSanta\Santa\Rudolph;
use JoliCode\SecretSanta\Santa\Spoiler;
use JoliCode\SecretSanta\Santa\UserLoader;
use JoliCode\SecretSanta\Statistic\StatisticCollector;
use Psr\Log\LoggerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;
use Symfony\Component\Form\FormError;
use Symfony\Component\Form\FormFactoryInterface;
use Symfony\Component\Form\SubmitButton;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Routing\RouterInterface;
use Twig\Environment;

class SantaController extends AbstractController
{
    private const SESSION_KEY_CONFIG = 'config';

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
        private Rudolph $rudolph,
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

        $config = new Config(
            $application->getCode(),
            $application->getOrganization(),
            $application->getAdmin(),
        );

        $this->saveConfig($request, $config);

        return $this->redirectToRoute('load_users', ['application' => $application->getCode()]);
    }

    #[Route('/load-users/{application}', name: 'load_users', methods: ['GET', 'POST'])]
    public function loadUsers(UserLoader $userLoader, Request $request, string $application): Response
    {
        $application = $this->getApplication($application);

        if (!$application->isAuthenticated()) {
            return new RedirectResponse($this->router->generate($application->getAuthenticationRoute()));
        }

        $config = $this->getConfigOrThrow404($request);

        if (!$request->isXmlHttpRequest()) {
            if ($config->areUsersLoaded()) {
                return new RedirectResponse($this->router->generate('participants', [
                    'application' => $application->getCode(),
                ]));
            }

            $content = $this->twig->render('santa/load_users.html.twig', [
                'application' => $application->getCode(),
                'count' => \count($config->getAvailableUsers()),
            ]);

            return new Response($content);
        }

        $error = false;

        try {
            $userLoader->loadUsers($config, $application);
        } catch (UserExtractionFailedException $e) {
            $this->logger->error($e->getMessage(), [
                'exception' => $e,
            ]);
            $this->bugsnag->notifyException($e, function ($report) {
                $report->setSeverity('info');
            });

            $error = true;
        }

        $this->saveConfig($request, $config);

        return new JsonResponse([
            'count' => \count($config->getAvailableUsers()),
            'error' => $error,
            'finished' => $config->areUsersLoaded(),
        ]);
    }

    #[Route('/participants/{application}', name: 'participants', methods: ['GET', 'POST'])]
    public function participants(Request $request, string $application): Response
    {
        $application = $this->getApplication($application);

        if (!$application->isAuthenticated()) {
            return new RedirectResponse($this->router->generate($application->getAuthenticationRoute()));
        }

        $config = $this->getConfigOrThrow404($request);
        $errors = [];

        if (!$config->areUsersLoaded()) {
            return new RedirectResponse($this->router->generate('load_users', ['application' => $application->getCode()]));
        }

        $availableUsers = $config->getAvailableUsers();

        if ($request->isMethod('POST') && $request->request->has('selectedUsers')) {
            $selectedUsers = $request->request->all('selectedUsers');

            if (\count($selectedUsers) < 2) {
                $errors[] = 'You have to select at least 2 users';
            } else {
                $config->resetUsers();
                $config->setSelectedUsers($request->request->all('selectedUsers'));
                $this->saveConfig($request, $config);

                return $this->redirectToRoute('exclusions', ['application' => $application->getCode()]);
            }
        }

        $content = $this->twig->render('santa/application/participants_' . $application->getCode() . '.html.twig', ['application' => $application->getCode(),
            'users' => $availableUsers,
            'selectedUsers' => $config->getSelectedUsers(),
            'groups' => $config->getGroups(),
            'errors' => $errors,
        ]);

        return new Response($content);
    }

    #[Route('/exclusions/{application}', name: 'exclusions', methods: ['GET', 'POST'])]
    public function exclusions(FormFactoryInterface $formFactory, Request $request, string $application): Response
    {
        $application = $this->getApplication($application);

        if (!$application->isAuthenticated()) {
            return new RedirectResponse($this->router->generate($application->getAuthenticationRoute()));
        }

        $errors = [];

        $config = $this->getConfigOrThrow404($request);
        $selectedUsers = $config->getSelectedUsers();

        /** @var true|false $areExclusionsAllowed */
        $areExclusionsAllowed = false; // \count($selectedUsers) <= 100;
        $form = null;

        // We remove exclusions from users that aren't selected anymore and create empty ones for those who are
        // and don't have any yet.
        $exclusions = [];
        if ($areExclusionsAllowed) {
            foreach ($config->getExclusions() as $userIdentifier => $excludedUsers) {
                if (!\in_array($userIdentifier, $selectedUsers, true)) {
                    continue;
                }
                $exclusions[$userIdentifier] = array_filter($excludedUsers, function ($excludedUserIdentifier) use ($selectedUsers) {
                    return \in_array($excludedUserIdentifier, $selectedUsers, true);
                });
            }
            foreach ($selectedUsers as $user) {
                $exclusions[$user] ??= [];
            }

            $config->setExclusions($exclusions);

            $builder = $formFactory->createBuilder(ExclusionsType::class, $config, [
                'config' => $config,
            ]);

            $form = $builder->getForm();

            $form->handleRequest($request);
        }

        if ($form && $form->isSubmitted()) {
            $areExclusionsValid = true;

            try {
                $config->setShuffledUsers($this->rudolph->associateUsers($config));
            } catch (RudolphException $e) {
                $form->addError(new FormError($e->getMessage()));
                $areExclusionsValid = false;
            }

            $this->saveConfig($request, $config);

            if ($form->isValid() && $areExclusionsValid) {
                return $this->redirectToRoute('message', ['application' => $application->getCode()]);
            }

            $errors = array_map(function (FormError $error) {
                return $error->getMessage();
            }, iterator_to_array($form->getErrors(true, false)));

            if ($errors) {
                $errors = array_unique($errors);
            }
        }

        $content = $this->twig->render('santa/application/exclusions_' . $application->getCode() . '.html.twig', [
            'application' => $application->getCode(),
            'admin' => $application->getAdmin(),
            'config' => $config,
            'errors' => $errors,
            'exclusions_allowed' => $areExclusionsAllowed,
            'form' => $form?->createView(),
        ]);

        return new Response($content);
    }

    #[Route('/message/{application}', name: 'message', methods: ['GET', 'POST'])]
    public function message(FormFactoryInterface $formFactory, Request $request, string $application): Response
    {
        $application = $this->getApplication($application);

        if (!$application->isAuthenticated()) {
            return new RedirectResponse($this->router->generate($application->getAuthenticationRoute()));
        }

        $errors = [];

        $config = $this->getConfigOrThrow404($request);

        $areNotesAllowed = \count($config->getSelectedUsers()) <= 200;
        if (!$areNotesAllowed) {
            $notes = []; // Empty = no notes
        } else {
            // We remove notes from users that aren't selected anymore, and create empty ones for those who are
            // and don't have any yet.
            $selectedUsersAsArray = $config->getSelectedUsers();
            $notes = array_filter($config->getNotes(), function ($userIdentifier) use ($selectedUsersAsArray) {
                return \in_array($userIdentifier, $selectedUsersAsArray, true);
            }, \ARRAY_FILTER_USE_KEY);
            foreach ($config->getSelectedUsers() as $user) {
                $notes[$user] ??= '';
            }
        }

        $config->setNotes($notes);

        $builder = $formFactory->createBuilder(MessageType::class, $config, [
            'selected-users' => $config->getSelectedUsers(),
        ]);

        $application->configureMessageForm($builder);

        $form = $builder->getForm();

        $form->handleRequest($request);

        if ($form->isSubmitted()) {
            $this->saveConfig($request, $config);

            if ($form->isValid()) {
                return $this->redirectToRoute('validate', ['application' => $application->getCode()]);
            }

            $errors = array_map(function (FormError $error) {
                return $error->getMessage();
            }, iterator_to_array($form->getErrors(true, false)));

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

    #[Route('/validate/{application}', name: 'validate', methods: ['GET', 'POST'])]
    public function validate(FormFactoryInterface $formFactory, Spoiler $spoiler, Request $request, string $application): Response
    {
        $application = $this->getApplication($application);

        if (!$application->isAuthenticated()) {
            return new RedirectResponse($this->router->generate($application->getAuthenticationRoute()));
        }

        $errors = [];

        $config = $this->getConfigOrThrow404($request);

        if (!$config->getShuffledUsers()) {
            if (\count($config->getSelectedUsers()) < 2) {
                return new RedirectResponse($this->router->generate('participants', ['application' => $application->getCode()]));
            }

            try {
                $config->setShuffledUsers($this->rudolph->associateUsers($config));
            } catch (RudolphException $e) {
                $errors[] = $e->getMessage();
            }
        }

        $form = $formFactory->createBuilder()
            ->add('shuffle', SubmitType::class)
            ->add('submit', SubmitType::class)
            ->getForm()
        ;

        $form->handleRequest($request);

        if (!$errors && $form->isSubmitted()) {
            if ($form->isValid()) {
                $shuffleButton = $form->get('shuffle');
                if ($shuffleButton instanceof SubmitButton && $shuffleButton->isClicked()) {
                    $config->setShuffledUsers($this->rudolph->associateUsers($config));
                    $this->saveConfig($request, $config);

                    $secretSanta = new SecretSanta(
                        'shuffle',
                        [],
                        $config
                    );
                    $this->statisticCollector->incrementShuffleCount($secretSanta);

                    return $this->redirectToRoute('validate', [
                        'application' => $application->getCode(),
                        'reshuffled' => 1,
                    ]);
                }

                $secretSanta = $this->prepareSecretSanta($config);
                $session = $request->getSession();
                $session->set(
                    $this->getSecretSantaSessionKey(
                        $secretSanta->getHash()
                    ),
                    $secretSanta
                );

                // Send a summary to the santa admin
                if ($config->getAdmin()) {
                    $code = $spoiler->encode($secretSanta);
                    $spoilUrl = $this->generateUrl('spoil', [], UrlGeneratorInterface::ABSOLUTE_URL);

                    $application->sendAdminMessage($secretSanta, $code, $spoilUrl);
                }

                return $this->redirectToRoute('send_messages', ['hash' => $secretSanta->getHash()]);
            }

            $errors = array_map(function (FormError $error) {
                return $error->getMessage();
            }, iterator_to_array($form->getErrors(true, false)));

            if ($errors) {
                $errors = array_unique($errors);
            }
        }

        $content = $this->twig->render('santa/application/validate_' . $application->getCode() . '.html.twig', [
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

        $config = $this->getConfigOrThrow404($request);

        $builder = $formFactory->createBuilder(MessageType::class, $config, [
            'selected-users' => $config->getSelectedUsers(),
        ]);

        $application->configureMessageForm($builder);

        $form = $builder->getForm();

        $form->handleRequest($request);

        if ($form->isSubmitted()) {
            $notes = array_filter($config->getNotes());

            $candidates = array_filter($notes ? array_keys($notes) : $config->getSelectedUsers(), function ($id) use ($application) {
                return $application->getAdmin()->getIdentifier() !== $id;
            });

            $receiver = $candidates ? $candidates[array_rand($candidates)] : $application->getAdmin()->getIdentifier();

            $formErrors = array_map(function (FormError $error) {
                return $error->getMessage();
            }, iterator_to_array($form->getErrors(true, false)));

            if ($formErrors) {
                $formErrors = array_unique($formErrors);
            }

            $errors = array_merge($errors, $formErrors);

            if ($form->isValid()) {
                $secretSanta = new SecretSanta(
                    'sample',
                    [],
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
        $application = $this->getApplication($secretSanta->getConfig()->getApplication());

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
        } catch (MessageSendFailedException $e) {
            $secretSanta->addError($e->getMessage(), $e->getRecipient()->getIdentifier());

            $this->logger->error($e->getMessage(), [
                'exception' => $e,
            ]);
            $this->bugsnag->notifyException($e, function ($report) {
                $report->setSeverity('info');
            });

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
            'application' => $secretSanta->getConfig()->getApplication(),
        ]);

        return new Response($content);
    }

    #[Route('/retry/{hash}', name: 'retry', methods: ['GET'])]
    public function retry(Request $request, string $hash): Response
    {
        $secretSanta = $this->getSecretSantaOrThrow404($request, $hash);

        $secretSanta->resetErrors();

        return $this->redirectToRoute('send_messages', ['hash' => $hash]);
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

        throw $this->createNotFoundException(\sprintf('Unknown application %s.', $code));
    }

    private function getSecretSantaSessionKey(string $hash): string
    {
        return \sprintf('secret-santa-%s', $hash);
    }

    private function prepareSecretSanta(Config $config): SecretSanta
    {
        $associatedUsers = $config->getShuffledUsers();

        $hash = md5(serialize($associatedUsers));

        return new SecretSanta(
            $hash,
            $associatedUsers,
            $config,
        );
    }

    private function saveConfig(Request $request, Config $config): void
    {
        $session = $request->getSession();
        $session->set(self::SESSION_KEY_CONFIG, $config);
    }

    private function getConfigOrThrow404(Request $request): Config
    {
        $session = $request->getSession();

        /** @var Config|null $config * */
        $config = $session->get(self::SESSION_KEY_CONFIG);

        if (!$config) {
            throw $this->createNotFoundException('No config found in session.');
        }

        return $config;
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
        $session->remove(self::SESSION_KEY_CONFIG);

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
