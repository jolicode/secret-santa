<?php

/*
 * This file is part of the Slack Secret Santa project.
 *
 * (c) JoliCode <coucou@jolicode.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace JoliCode\SecretSanta\Controller;

use Joli\SlackSecretSanta\Application\TeamsApplication;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\RouterInterface;
use TheNetworg\OAuth2\Client\Provider\Azure;

class TeamsController extends AbstractController
{
    private $router;
    private $teamsClientId;
    private $teamsClientSecret;

    public function __construct(RouterInterface $router, $teamsClientId, $teamsClientSecret)
    {
        $this->router = $router;
        $this->teamsClientId = $teamsClientId;
        $this->teamsClientSecret = $teamsClientSecret;
    }

    public function authenticate(Request $request): Response
    {
        $session = $request->getSession();

        $provider = new Azure([
            'clientId' => $this->teamsClientId,
            'clientSecret' => $this->teamsClientSecret,
            'redirectUri' => $this->router->generate('teams_authenticate', [], RouterInterface::ABSOLUTE_URL),
        ]);

        if ($request->query->has('error')) {
            return $this->redirectToRoute('homepage');
        }

        if (!$request->query->has('code')) {
            // If we don't have an authorization code then get one

            $authUrl = $provider->getAuthorizationUrl([]);
            $session->set(TeamsApplication::SESSION_KEY_STATE, $provider->getState());

            return new RedirectResponse($authUrl);
            // Check given state against previously stored one to mitigate CSRF attack
        } elseif (empty($request->query->get('state')) || ($request->query->get('state') !== $session->get(TeamsApplication::SESSION_KEY_STATE))) {
            $session->remove(TeamsApplication::SESSION_KEY_STATE);

            return new Response('Invalid state', 401);
        }

        // Try to get an access token (using the authorization code grant)
        $token = $provider->getAccessToken('authorization_code', [
            'code' => $request->query->get('code'),
            'resource' => 'https://graph.windows.net', // @todo remove?
        ]);

        // Who Am I?
        try {
            $me = $provider->get("me", $token);
            $resourceOwner = $provider->getResourceOwner($token);

            // https://developer.microsoft.com/en-us/graph/docs/api-reference/beta/api/group_list_members

            dump($token, $me, $resourceOwner);
            die();
            //$user = $provider->getResourceOwner($token);
        } catch (\Exception $e) {
            // Failed to get user details
            return new RedirectResponse($this->router->generate('homepage'));
        }

//        $discordApplication->setToken($token);
//        $discordApplication->setAdmin(new User($user->getId(), $user->getUsername()));
//        $discordApplication->setGuildId($request->query->get('guild_id'));
//
//        return new RedirectResponse($this->router->generate('run', [
//            'application' => $discordApplication->getCode(),
//        ]));
    }

    public function incomingBot(Request $request): Response
    {
        dump($request);
        // @todo

        return new Response();
    }
}
