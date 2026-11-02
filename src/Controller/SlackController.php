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
use JoliCode\SecretSanta\Model\Channel;
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
                    'im:write',
                    'team:read',
                ],
            ];
            $authUrl = $provider->getAuthorizationUrl($options);

            $session->set(SlackApplication::SESSION_KEY_STATE, $provider->getState());

            return new RedirectResponse($authUrl);
        }
        // Check given state against previously stored one to mitigate CSRF attack
        if (empty($request->query->get('state')) || ($request->query->get('state') !== $session->get(SlackApplication::SESSION_KEY_STATE))) {
            $session->remove(SlackApplication::SESSION_KEY_STATE);

            throw new AuthenticationException(SlackApplication::APPLICATION_CODE, 'Your sign-in session has expired, or the link was opened in another browser.');
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
            throw new AuthenticationException(SlackApplication::APPLICATION_CODE, 'Slack did not answer properly when we asked for your account.', $e);
        }

        $slackApplication->setToken($appToken);
        $slackApplication->setAdmin($admin);
        $slackApplication->setChannels([]);

        return $this->redirectToRoute('slack_channels');
    }

    /**
     * Let the admin restrict the participants to the members of some channels, or include the whole workspace.
     */
    #[Route('/auth/slack/channels', name: 'slack_channels', methods: ['GET', 'POST'])]
    public function channels(Request $request, SlackApplication $slackApplication, UserExtractor $userExtractor): Response
    {
        $admin = $slackApplication->getAdmin();

        if (!$slackApplication->isAuthenticated() || !$admin) {
            return $this->redirectToRoute('slack_authenticate');
        }

        if ($request->isMethod('POST') && 'workspace' === $request->request->get('scope')) {
            $slackApplication->setChannels([]);

            return $this->redirectToRoute('run', ['application' => $slackApplication->getCode()]);
        }

        $token = $slackApplication->getToken()->getToken();
        $channels = $userExtractor->listChannels($token, $admin);
        $error = null;

        if ($request->isMethod('POST')) {
            $selected = array_intersect_key($channels, array_flip($request->request->all('channels')));

            if ([] !== $selected) {
                $groups = [];

                foreach ($selected as $channel) {
                    $groups[$channel->id] = $userExtractor->loadMembers($token, $channel);
                }

                $slackApplication->setChannels($groups);

                if (array_filter($selected, static fn (Channel $channel) => $channel->needsInvitation())) {
                    return $this->redirectToRoute('slack_channels_invitation');
                }

                return $this->redirectToRoute('run', ['application' => $slackApplication->getCode()]);
            }

            $error = 'Select at least one channel, or include everyone in the workspace.';
        }

        return $this->render('slack/channels.html.twig', [
            'channels' => $channels,
            'error' => $error,
            'selected' => array_keys($slackApplication->getChannels()),
        ]);
    }

    /**
     * The bot can only message the external members (Slack Connect) of a channel it has been invited in:
     * list the chosen shared channels still missing it, until there is none left.
     */
    #[Route('/auth/slack/channels/invitation', name: 'slack_channels_invitation', methods: ['GET'])]
    public function channelsInvitation(SlackApplication $slackApplication, UserExtractor $userExtractor): Response
    {
        $admin = $slackApplication->getAdmin();

        if (!$slackApplication->isAuthenticated() || !$admin) {
            return $this->redirectToRoute('slack_authenticate');
        }

        $channels = array_intersect_key($userExtractor->listChannels($slackApplication->getToken()->getToken(), $admin), $slackApplication->getChannels());
        $channels = array_filter($channels, static fn (Channel $channel) => $channel->needsInvitation());

        if ([] === $channels) {
            return $this->redirectToRoute('run', ['application' => $slackApplication->getCode()]);
        }

        return $this->render('slack/channels_invitation.html.twig', [
            'channels' => $channels,
        ]);
    }
}
