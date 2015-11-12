<?php

namespace Joli\SlackSecretSanta\Controller;

use Bramdevries\Oauth\Client\Provider\Slack;
use CL\Slack\Payload\ChatPostMessagePayload;
use CL\Slack\Payload\ChatPostMessagePayloadResponse;
use CL\Slack\Transport\ApiClient;
use Joli\SlackSecretSanta\SecretDispatcher;
use Joli\SlackSecretSanta\UserExtractor;
use League\OAuth2\Client\Token\AccessToken;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\Session\SessionInterface;
use Symfony\Component\Routing\RouterInterface;

class SantaController
{
    const STATE_SESSION_KEY = 'santa.slack.state';
    const TOKEN_SESSION_KEY = 'santa.slack.token';

    private $session;
    private $router;
    private $slackClientId;
    private $slackClientSecret;
    private $twig;

    public function __construct(SessionInterface $session, RouterInterface $router, \Twig_Environment $twig, $slackClientId, $slackClientSecret)
    {
        $this->session           = $session;
        $this->router            = $router;
        $this->slackClientId     = $slackClientId;
        $this->slackClientSecret = $slackClientSecret;
        $this->twig              = $twig;
    }

    public function homepage(Request $request)
    {
        $token = $this->session->get(self::TOKEN_SESSION_KEY);

        if (!($token instanceof AccessToken)) {
            return new RedirectResponse($this->router->generate('authenticate'));
        }

        $apiClient = new ApiClient($token->getToken());

        if ($request->isMethod('POST')) {
            $selectedUsers = $request->request->get('users');
            $message       = $request->request->get('message');

            $secretDispatcher = new SecretDispatcher($apiClient);
            $secretDispatcher->dispatchTo($selectedUsers, $message);

            return new Response('Thank you, messages have been sent!');
        }

        try {
            $userExtractor  = new UserExtractor($apiClient);
            $users          = $userExtractor->extractAll();
            $content = $this->twig->render('index.html.twig', ['users' => $users]);
            return new Response($content);

        } catch (\RuntimeException $e) {
            return new RedirectResponse($this->router->generate('authenticate'));
        }
    }

    /**
     * Called when someone type /secretsanta in a group or channel.
     *
     * @todo We need a way to "install" this command, and this needs storage of tokens too...
     *
     * @param Request $request
     *
     * @return Response
     */
    public function command(Request $request)
    {
        return new Response('Not implemented yet.');
    }

    /**
     * Ask for Slack authentication and store the AccessToken in Session.
     *
     * @param Request $request
     *
     * @return RedirectResponse|Response
     */
    public function authenticate(Request $request)
    {
        $provider = new Slack([
            'clientId'          => $this->slackClientId,
            'clientSecret'      => $this->slackClientSecret,
            'redirectUri'       => $this->router->generate('authenticate', [], RouterInterface::ABSOLUTE_URL),
        ]);

        if (!$request->query->has('code')) {
            // If we don't have an authorization code then get one
            $options = [
                'scope' => ['chat:write:bot', 'users:read', 'channels:read'], // array or string
            ];
            $authUrl = $provider->getAuthorizationUrl($options);

            $this->session->set(self::STATE_SESSION_KEY, $provider->getState());

            return new RedirectResponse($authUrl);
        } elseif (empty($request->query->get('state')) || ($request->query->get('state') !== $this->session->get(self::STATE_SESSION_KEY))) {
            $this->session->remove(self::STATE_SESSION_KEY);

            return new Response('Invalid states.', 401);
        } else {
            // Try to get an access token (using the authorization code grant)
            $token = $provider->getAccessToken('authorization_code', [
                'code' => $request->query->get('code'),
            ]);

            $this->session->set(self::TOKEN_SESSION_KEY, $token);

            return new RedirectResponse($this->router->generate('homepage'));
        }
    }
}
