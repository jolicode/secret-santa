<?php

/*
 * This file is part of the Slack Secret Santa project.
 *
 * (c) JoliCode <coucou@jolicode.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Joli\SlackSecretSanta\Controller;

use AdamPaterson\OAuth2\Client\Provider\Slack;
use CL\Slack\Payload\AuthTestPayload;
use CL\Slack\Payload\AuthTestPayloadResponse;
use CL\Slack\Transport\ApiClient;
use Joli\SlackSecretSanta\Application\SlackApplication;
use Joli\SlackSecretSanta\User;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\RouterInterface;

class SlackController extends AbstractController
{
    private $slackClientId;
    private $slackClientSecret;
    private $router;

    public function __construct(string $slackClientId, string $slackClientSecret, RouterInterface $router)
    {
        $this->router = $router;
        $this->slackClientId = $slackClientId;
        $this->slackClientSecret = $slackClientSecret;
    }

    /**
     * Ask for Slack authentication and store the AccessToken in Session.
     */
    public function authenticate(Request $request, ApiClient $apiClient, SlackApplication $slackApplication): Response
    {
        $session = $request->getSession();

        $provider = new Slack([
            'clientId' => $this->slackClientId,
            'clientSecret' => $this->slackClientSecret,
            'redirectUri' => $this->router->generate('slack_authenticate', [], RouterInterface::ABSOLUTE_URL),
        ]);

        if ($request->query->has('error')) {
            return $this->redirectToRoute('homepage');
        }

        if (!$request->query->has('code')) {
            // If we don't have an authorization code then get one
            $options = [
                'scope' => [
                    'chat:write:bot',
                    'users:read',
                ], // array or string
            ];
            $authUrl = $provider->getAuthorizationUrl($options);

            $session->set(SlackApplication::SESSION_KEY_STATE, $provider->getState());

            return new RedirectResponse($authUrl);
        // Check given state against previously stored one to mitigate CSRF attack
        } elseif (empty($request->query->get('state')) || ($request->query->get('state') !== $session->get(SlackApplication::SESSION_KEY_STATE))) {
            $session->remove(SlackApplication::SESSION_KEY_STATE);

            return new Response('Invalid state', 401);
        }

        // Try to get an access token (using the authorization code grant)
        $token = $provider->getAccessToken('authorization_code', [
            'code' => $request->query->get('code'),
        ]);

        // Who Am I?
        $test = new AuthTestPayload();
        /** @var AuthTestPayloadResponse $response */
        $response = $apiClient->send($test, $token->getToken());

        if ($response->isOk()) {
            $slackApplication->setToken($token);
            $slackApplication->setAdmin(new User($response->getUserId(), $response->getUsername()));

            return new RedirectResponse($this->router->generate('run', [
                'application' => $slackApplication->getCode(),
            ]));
        }

        return new RedirectResponse($this->router->generate('homepage'));
    }
}
