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

use JoliCode\SecretSanta\Application\WebexApplication;
use JoliCode\SecretSanta\Exception\AuthenticationException;
use JoliCode\SecretSanta\Model\ApplicationToken;
use JoliCode\SecretSanta\Webex\MessageSender;
use JoliCode\SecretSanta\Webex\UserExtractor;
use JoliCode\SecretSanta\Webex\WebexProvider;
use League\OAuth2\Client\Token\AccessToken;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Routing\RouterInterface;

class WebexController extends AbstractController
{
    public function __construct(
        private string $webexClientId,
        private string $webexClientSecret,
        private RouterInterface $router,
    ) {
    }

    /**
     * Ask for Webex authentication and store the AccessToken in Session.
     */
    #[Route('/auth/webex', name: 'webex_authenticate', methods: ['GET'])]
    public function authenticate(Request $request, WebexApplication $webexApplication, UserExtractor $userExtractor): Response
    {
        $session = $request->getSession();

        $provider = new WebexProvider([
            'clientId' => $this->webexClientId,
            'clientSecret' => $this->webexClientSecret,
            'redirectUri' => $this->router->generate('webex_authenticate', [], RouterInterface::ABSOLUTE_URL),
        ]);

        if ($request->query->has('error')) {
            if ('invalid_scope' === $request->query->get('error')) {
                throw new AuthenticationException(WebexApplication::APPLICATION_CODE, 'Cannot get the required permissions.');
            }

            return $this->redirectToRoute('homepage');
        }

        if (!$request->query->has('code')) {
            // If we don't have an authorization code then get one
            $options = [
                'scope' => [
                    'spark:kms', // This scope is required to give your integration permission to interact with encrypted content (such as messages).
                    'spark:memberships_read',
                    'spark:rooms_read',
                    'spark:people_read',
                ],
            ];
            $authUrl = $provider->getAuthorizationUrl($options);

            $session->set(WebexApplication::SESSION_KEY_STATE, $provider->getState());

            return new RedirectResponse($authUrl);
        }

        // Check given state against previously stored one to mitigate CSRF attack
        if (!$request->query->get('state') || $request->query->get('state') !== $session->get(WebexApplication::SESSION_KEY_STATE)) {
            $session->remove(WebexApplication::SESSION_KEY_STATE);

            throw new AuthenticationException(WebexApplication::APPLICATION_CODE, 'Invalid OAuth state.');
        }

        try {
            // Try to get an access token (using the authorization code grant)
            /** @var AccessToken $token */
            $token = $provider->getAccessToken('authorization_code', [
                'code' => $request->query->get('code'),
            ]);

            $appToken = new ApplicationToken($token->getToken());
        } catch (\Exception $e) {
            throw new AuthenticationException(WebexApplication::APPLICATION_CODE, 'Failed to login on Webex.', $e);
        }

        $admin = $userExtractor->getMe($token->getToken());
        $webexApplication->setToken($appToken);
        $webexApplication->setAdmin($admin);

        return new RedirectResponse($this->router->generate('run', [
            'application' => $webexApplication->getCode(),
        ]));
    }

    #[Route('/landing/webex', name: 'webex_landing', methods: ['GET'])]
    public function landing(): Response
    {
        return $this->render('webex/landing.html.twig');
    }

    #[Route('/bot/webex', name: 'webex_bot', methods: ['POST'])]
    public function bot(Request $request, MessageSender $messageSender): Response
    {
        $eventData = $request->toArray();

        if ('messages' !== $eventData['resource']) {
            return new Response();
        }

        if ('created' !== $eventData['event']) {
            return new Response();
        }

        // Avoid to answer to ourselves
        if ('secret-santa@webex.bot' === $eventData['data']['personEmail']) {
            return new Response();
        }

        $messageSender->sendDummyBotAnswer($eventData['data']['personId']);

        return new Response();
    }
}
