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

use AdamPaterson\OAuth2\Client\Provider\Slack;
use AdamPaterson\OAuth2\Client\Provider\SlackResourceOwner;
use JoliCode\SecretSanta\Application\SlackApplication;
use JoliCode\SecretSanta\Exception\AuthenticationException;
use JoliCode\SecretSanta\Model\User;
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
    public function authenticate(Request $request, SlackApplication $slackApplication): Response
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
                    'usergroups:read',
                ],
            ];
            $authUrl = $provider->getAuthorizationUrl($options);

            $session->set(SlackApplication::SESSION_KEY_STATE, $provider->getState());

            return new RedirectResponse($authUrl);
        // Check given state against previously stored one to mitigate CSRF attack
        } elseif (empty($request->query->get('state')) || ($request->query->get('state') !== $session->get(SlackApplication::SESSION_KEY_STATE))) {
            $session->remove(SlackApplication::SESSION_KEY_STATE);

            throw new AuthenticationException('Invalid OAuth state.');
        }

        try {
            // Try to get an access token (using the authorization code grant)
            $token = $provider->getAccessToken('authorization_code', [
                'code' => $request->query->get('code'),
            ]);

            // Who Am I?
            /** @var SlackResourceOwner $user */
            $user = $provider->getResourceOwner($token);
        } catch (\Exception $e) {
            throw new AuthenticationException('Failed to retrieve data from Slack.', 0, $e);
        }

        $slackApplication->setToken($token);
        $slackApplication->setAdmin(new User($user->getId(), $user->getRealName()));

        return new RedirectResponse($this->router->generate('run', [
            'application' => $slackApplication->getCode(),
        ]));
    }
}
