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
use JoliCode\SecretSanta\Exception\AuthenticationException;
use JoliCode\SecretSanta\Model\User;
use League\OAuth2\Client\Token\AccessToken;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Routing\RouterInterface;
use Wohali\OAuth2\Client\Provider\Discord;
use Wohali\OAuth2\Client\Provider\DiscordResourceOwner;

class DiscordController extends AbstractController
{
    private $discordClientId;
    private $discordClientSecret;
    private $router;

    public function __construct(string $discordClientId, string $discordClientSecret, RouterInterface $router)
    {
        $this->router = $router;
        $this->discordClientId = $discordClientId;
        $this->discordClientSecret = $discordClientSecret;
    }

    /**
     * Ask for Discord authentication and store the AccessToken in Session.
     */
    #[Route('/auth/discord', name: 'discord_authenticate', methods: ['GET'])]
    public function authenticate(Request $request, DiscordApplication $discordApplication): Response
    {
        $session = $request->getSession();

        $provider = new Discord([
            'clientId' => $this->discordClientId,
            'clientSecret' => $this->discordClientSecret,
            'redirectUri' => $this->router->generate('discord_authenticate', [], RouterInterface::ABSOLUTE_URL),
        ]);

        if ($request->query->has('error')) {
            return $this->redirectToRoute('homepage');
        }

        if (!$request->query->has('code')) {
            // If we don't have an authorization code then get one
            $options = [
                'scope' => [
                    'identify',
                    'bot',
                ],
                //'permissions' => '2048',
            ];
            $authUrl = $provider->getAuthorizationUrl($options);

            $session->set(DiscordApplication::SESSION_KEY_STATE, $provider->getState());

            return new RedirectResponse($authUrl);
        }
        // Check given state against previously stored one to mitigate CSRF attack
        if (empty($request->query->get('state')) || ($request->query->get('state') !== $session->get(DiscordApplication::SESSION_KEY_STATE))) {
            $session->remove(DiscordApplication::SESSION_KEY_STATE);

            throw new AuthenticationException(DiscordApplication::APPLICATION_CODE, 'Invalid OAuth state.');
        }

        if (!$request->query->has('guild_id')) {
            throw new AuthenticationException(DiscordApplication::APPLICATION_CODE, 'No guild_id found.');
        }

        try {
            // Try to get an access token (using the authorization code grant)
            /** @var AccessToken $token */
            $token = $provider->getAccessToken('authorization_code', [
                'code' => $request->query->get('code'),
            ]);

            // Who Am I?
            /** @var DiscordResourceOwner $user */
            $user = $provider->getResourceOwner($token);
        } catch (\Exception $e) {
            throw new AuthenticationException(DiscordApplication::APPLICATION_CODE, 'Failed to retrieve data from Discord.', $e);
        }

        $discordApplication->setToken($token);
        $discordApplication->setAdmin(new User($user->getId(), $user->getUsername()));
        $discordApplication->setGuildId($request->query->getInt('guild_id'));

        return new RedirectResponse($this->router->generate('run', [
            'application' => $discordApplication->getCode(),
        ]));
    }
}
