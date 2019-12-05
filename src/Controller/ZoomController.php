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

use JoliCode\SecretSanta\Application\DiscordApplication;
use JoliCode\SecretSanta\Application\ZoomApplication;
use JoliCode\SecretSanta\Exception\AuthenticationException;
use JoliCode\SecretSanta\Model\User;
use League\OAuth2\Client\Provider\Zoom;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\RouterInterface;
use Wohali\OAuth2\Client\Provider\Discord;
use Wohali\OAuth2\Client\Provider\DiscordResourceOwner;

class ZoomController extends AbstractController
{
    private $router;
    private $zoomClientId;
    private $zoomClientSecret;

    public function __construct(string $zoomClientId, string $zoomClientSecret, RouterInterface $router)
    {
        $this->router = $router;
        $this->zoomClientId = $zoomClientId;
        $this->zoomClientSecret = $zoomClientSecret;
    }

    /**
     * Ask for Zoom authentication and store the AccessToken in Session.
     */
    public function authenticate(Request $request, ZoomApplication $discordApplication): Response
    {
        $session = $request->getSession();

        $provider = new Zoom([
            'clientId' => $this->zoomClientId,
            'clientSecret' => $this->zoomClientSecret,
            'redirectUri' => $this->router->generate('zoom_authenticate', [], RouterInterface::ABSOLUTE_URL),
        ]);

        if ($request->query->has('error')) {
            return $this->redirectToRoute('homepage');
        }

        if (!$request->query->has('code')) {
            $options = [
                'scope' => ['']
            ];

            $authUrl = $provider->getAuthorizationUrl($options);

            $session->set(ZoomApplication::SESSION_KEY_STATE, $provider->getState());

            return new RedirectResponse($authUrl);
        // Check given state against previously stored one to mitigate CSRF attack
        } elseif (empty($request->query->get('state')) || ($request->query->get('state') !== $session->get(ZoomApplication::SESSION_KEY_STATE))) {
            $session->remove(ZoomApplication::SESSION_KEY_STATE);

            throw new AuthenticationException(ZoomApplication::APPLICATION_CODE, 'Invalid OAuth state.');
        }

        try {
            // Try to get an access token (using the authorization code grant)
            $token = $provider->getAccessToken('authorization_code', [
                'code' => $request->query->get('code'),
            ]);

            dump($token);
            die();

            // Who Am I?
            /** @var DiscordResourceOwner $user */
            $user = $provider->getResourceOwner($token);
        } catch (\Exception $e) {
            throw new AuthenticationException(ZoomApplication::APPLICATION_CODE, 'Failed to retrieve data from Discord.', $e);
        }

        $discordApplication->setToken($token);
        $discordApplication->setAdmin(new User($user->getId(), $user->getUsername()));

        return new RedirectResponse($this->router->generate('run', [
            'application' => $discordApplication->getCode(),
        ]));
    }
}
