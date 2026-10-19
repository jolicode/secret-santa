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

use JoliCode\SecretSanta\Application\SlackApplication;
use JoliCode\SecretSanta\Exception\AuthenticationException;
use JoliCode\SecretSanta\Model\ApplicationToken;
use JoliCode\SecretSanta\Slack\SlackProvider;
use JoliCode\SecretSanta\Slack\UserExtractor;
use League\OAuth2\Client\Token\AccessToken;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Routing\RouterInterface;

class SlackController extends AbstractController
{
    private const SESSION_KEY_SHARED_CHANNEL_CANDIDATES = 'santa.slack.shared_channel_candidates';

    public function __construct(
        private string $slackClientId,
        private string $slackClientSecret,
        private RouterInterface $router,
    ) {
    }

    /**
     * Ask for Slack authentication and store the AccessToken in Session.
     */
    #[Route('/auth/slack', name: 'slack_authenticate', methods: ['GET'])]
    public function authenticate(Request $request, SlackApplication $slackApplication, UserExtractor $userExtractor): Response
    {
        $session = $request->getSession();

        $provider = new SlackProvider([
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
                    'chat:write',
                    'users:read',
                    'usergroups:read',
                    'channels:read',
                    'groups:read',
                ],
            ];
            $authUrl = $provider->getAuthorizationUrl($options);

            $session->set(SlackApplication::SESSION_KEY_STATE, $provider->getState());

            return new RedirectResponse($authUrl);
        }
        // Check given state against previously stored one to mitigate CSRF attack
        if (empty($request->query->get('state')) || ($request->query->get('state') !== $session->get(SlackApplication::SESSION_KEY_STATE))) {
            $session->remove(SlackApplication::SESSION_KEY_STATE);

            throw new AuthenticationException(SlackApplication::APPLICATION_CODE, 'Invalid OAuth state.');
        }

        try {
            // Try to get an access token (using the authorization code grant)
            /** @var AccessToken $token */
            $token = $provider->getAccessToken('authorization_code', [
                'code' => $request->query->get('code'),
            ]);

            $appToken = new ApplicationToken($token->getToken(), ['team' => $token->getValues()['team']['name']]);

            $admin = $userExtractor->getUser($token->getToken(), $token->getValues()['authed_user']['id']);
        } catch (\Exception $e) {
            throw new AuthenticationException(SlackApplication::APPLICATION_CODE, 'Failed to retrieve data from Slack.', $e);
        }

        $slackApplication->setToken($appToken);
        $slackApplication->setAdmin($admin);
        $slackApplication->setSharedChannels([]);

        $sharedChannels = $userExtractor->listSharedChannels($token->getToken());

        if ($sharedChannels) {
            $session->set(self::SESSION_KEY_SHARED_CHANNEL_CANDIDATES, $sharedChannels);

            return $this->redirectToRoute('slack_shared_channels');
        }

        return new RedirectResponse($this->router->generate('run', [
            'application' => $slackApplication->getCode(),
        ]));
    }

    /**
     * Let the admin pick which Slack Connect channels bring external participants (none by default).
     */
    #[Route('/auth/slack/shared-channels', name: 'slack_shared_channels', methods: ['GET', 'POST'])]
    public function sharedChannels(Request $request, SlackApplication $slackApplication): Response
    {
        if (!$slackApplication->isAuthenticated()) {
            return $this->redirectToRoute('slack_authenticate');
        }

        /** @var array<string, string> $candidates */
        $candidates = $request->getSession()->get(self::SESSION_KEY_SHARED_CHANNEL_CANDIDATES, []);

        if ($request->isMethod('POST')) {
            $selected = array_intersect_key($candidates, array_flip($request->request->all('channels')));
            $slackApplication->setSharedChannels($selected);

            return $this->redirectToRoute('run', ['application' => $slackApplication->getCode()]);
        }

        return $this->render('slack/shared_channels.html.twig', [
            'channels' => $candidates,
        ]);
    }
}
