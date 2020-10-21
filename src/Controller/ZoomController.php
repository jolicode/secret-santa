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

use JoliCode\SecretSanta\Application\ZoomApplication;
use JoliCode\SecretSanta\Exception\AuthenticationException;
use JoliCode\SecretSanta\Model\User;
use League\OAuth2\Client\Provider\Zoom;
use League\OAuth2\Client\Provider\ZoomResourceOwner;
use League\OAuth2\Client\Token\AccessToken;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\RouterInterface;

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
    public function authenticate(Request $request, ZoomApplication $zoomApplication): Response
    {
        if ($request->query->has('error')) {
            return $this->redirectToRoute('homepage');
        }

        $provider = new Zoom([
            'clientId' => $this->zoomClientId,
            'clientSecret' => $this->zoomClientSecret,
            'redirectUri' => $this->router->generate('zoom_authenticate', [], RouterInterface::ABSOLUTE_URL),
        ]);
        $session = $request->getSession();

        if (!$request->query->has('code')) {
            $options = [
                'scope' => [
                    'imchat:bot', // from https://marketplace.zoom.us/docs/guides/chatbots/sending-messages
                    'imchat:write:admin',
                    'user:read:admin', // needed for getResourceOwner
                    'contact:read:admin',
                    //'chat_message:write', For "user" api only, we are "account" level app
                ],
            ];

            $authUrl = $provider->getAuthorizationUrl($options);
            $session->set(ZoomApplication::SESSION_KEY_STATE, $provider->getState());

            return new RedirectResponse($authUrl);
        // Check given state against previously stored one to mitigate CSRF attack
        } elseif (empty($request->query->get('state')) || $request->query->get('state') !== $session->get(ZoomApplication::SESSION_KEY_STATE)) {
            $session->remove(ZoomApplication::SESSION_KEY_STATE);

            throw new AuthenticationException(ZoomApplication::APPLICATION_CODE, 'Invalid OAuth state.');
        }

        try {
            /** @var AccessToken $botToken */
            $botToken = $provider->getAccessToken('client_credentials');

            /** @var AccessToken $token */
            $token = $provider->getAccessToken('authorization_code', [
                'code' => $request->query->get('code'),
            ]);

            /** @var ZoomResourceOwner $user */
            $user = $provider->getResourceOwner($token);
        } catch (\Exception $e) {
            throw new AuthenticationException(ZoomApplication::APPLICATION_CODE, 'Failed to retrieve data from Zoom.', $e);
        }

        $zoomApplication->setToken($token);
        $zoomApplication->setBotToken($botToken);
        $userData = $user->toArray();

        $zoomApplication->setAdmin(new User($userData['id'], $userData['first_name'] . ' ' . $userData['last_name']));
        $zoomApplication->setAccountId($userData['account_id']);

        return new RedirectResponse($this->router->generate('run', [
            'application' => $zoomApplication->getCode(),
        ]));
    }
}
