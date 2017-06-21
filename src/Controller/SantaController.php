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
use CL\Slack\Transport\ApiClient;
use GuzzleHttp\Client;
use Joli\SlackSecretSanta\Rudolph;
use Joli\SlackSecretSanta\SecretDispatcher;
use Joli\SlackSecretSanta\SecretSanta;
use Joli\SlackSecretSanta\UserExtractor;
use League\OAuth2\Client\Token\AccessToken;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\Session\SessionInterface;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Routing\RouterInterface;

class SantaController
{
    const STATE_SESSION_KEY = 'santa.slack.state';
    const TOKEN_SESSION_KEY = 'santa.slack.token';
    const USER_ID_SESSION_KEY = 'santa.slack.user_id';

    private $slackClientId;
    private $slackClientSecret;
    private $session;
    private $router;
    private $twig;

    public function __construct($slackClientId, $slackClientSecret, SessionInterface $session, RouterInterface $router, \Twig_Environment $twig)
    {
        $this->session = $session;
        $this->router = $router;
        $this->slackClientId = $slackClientId;
        $this->slackClientSecret = $slackClientSecret;
        $this->twig = $twig;
    }

    public function homepage()
    {
        $content = $this->twig->render('index.html.twig');

        return new Response($content);
    }

    public function run(Request $request)
    {
        $token = $this->session->get(self::TOKEN_SESSION_KEY);
        $userId = $this->session->get(self::USER_ID_SESSION_KEY);

        if (!($token instanceof AccessToken)) {
            return new RedirectResponse($this->router->generate('authenticate'));
        }

        $apiClient = $this->getApiClient($token);

        $selectedUsers = [];
        $message = null;
        $errors = [];

        if ($request->isMethod('POST')) {
            $selectedUsers = $request->request->get('users');
            $message = $request->request->get('message');

            $errors = $this->validate($selectedUsers, $message);

            if (count($errors) < 1) {
                $associatedUsers = (new Rudolph())->associateUsers($selectedUsers);
                $hash = md5(serialize($associatedUsers));

                $secretSanta = new SecretSanta($hash, $associatedUsers, $userId, str_replace('```', '', $message));

                (new SecretDispatcher($apiClient))->dispatchRemainingMessages($secretSanta);

                $request->getSession()->set(
                    $this->getSecretSantaSessionKey(
                        $secretSanta->getHash()
                    ), $secretSanta
                );

                return new RedirectResponse($this->router->generate('finish', ['hash' => $secretSanta->getHash()]));
            }
        }

        try {
            $userExtractor = new UserExtractor($apiClient);
            $users = $userExtractor->extractAll();
            $content = $this->twig->render('run.html.twig', [
                'users' => $users,
                'selectedUsers' => $selectedUsers,
                'message' => $message,
                'errors' => $errors,
            ]);

            return new Response($content);
        } catch (\RuntimeException $e) {
            return new RedirectResponse($this->router->generate('authenticate'));
        }
    }

    public function finish(Request $request, $hash)
    {
        $secretSanta = $this->getSecretSantaOrThrow404($request, $hash);

        $content = $this->twig->render('finish.html.twig', [
            'secretSanta' => $secretSanta,
        ]);

        return new Response($content);
    }

    public function summary(Request $request, $hash)
    {
        $secretSanta = $this->getSecretSantaOrThrow404($request, $hash);

        $content = $this->twig->render('summary.txt.twig', [
            'secretSanta' => $secretSanta,
        ]);

        $response = new Response($content);

        $response->headers->set('Cache-Control', 'private');
        $response->headers->set('Content-type', 'text/plain');
        $response->headers->set('Content-Disposition', 'attachment; filename="summary.txt";');

        return $response;
    }

    public function retry(Request $request, $hash)
    {
        $secretSanta = $this->getSecretSantaOrThrow404($request, $hash);

        $token = $this->session->get(self::TOKEN_SESSION_KEY);

        if (!($token instanceof AccessToken)) {
            return new RedirectResponse($this->router->generate('authenticate'));
        }

        $apiClient = $this->getApiClient($token);

        (new SecretDispatcher($apiClient))->dispatchRemainingMessages($secretSanta);

        $request->getSession()->set(
            $this->getSecretSantaSessionKey(
                $secretSanta->getHash()
            ), $secretSanta
        );

        return new RedirectResponse($this->router->generate('finish', ['hash' => $secretSanta->getHash()]));
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
            'clientId' => $this->slackClientId,
            'clientSecret' => $this->slackClientSecret,
            'redirectUri' => $this->router->generate('authenticate', [], RouterInterface::ABSOLUTE_URL),
        ]);

        if (!$request->query->has('code')) {
            // If we don't have an authorization code then get one
            $options = [
                'scope' => [
                    'chat:write:bot',
                    'users:read',
                ], // array or string
            ];
            $authUrl = $provider->getAuthorizationUrl($options);

            $this->session->set(self::STATE_SESSION_KEY, $provider->getState());

            return new RedirectResponse($authUrl);
        // Check given state against previously stored one to mitigate CSRF attack
        } elseif (empty($request->query->get('state')) || ($request->query->get('state') !== $this->session->get(self::STATE_SESSION_KEY))) {
            $this->session->remove(self::STATE_SESSION_KEY);

            return new Response('Invalid state', 401);
        }

        // Try to get an access token (using the authorization code grant)
        $token = $provider->getAccessToken('authorization_code', [
            'code' => $request->query->get('code'),
        ]);

        // Who Am I?
        $test = new AuthTestPayload();
        $response = $this->getApiClient($token)->send($test);

        if ($response->isOk()) {
            $this->session->set(self::TOKEN_SESSION_KEY, $token);
            $this->session->set(self::USER_ID_SESSION_KEY, $response->getUserId());

            return new RedirectResponse($this->router->generate('run'));
        }

        return new RedirectResponse($this->router->generate('homepage'));
    }

    /**
     * @param AccessToken $token
     *
     * @return ApiClient
     */
    private function getApiClient(AccessToken $token)
    {
        return new ApiClient($token->getToken(), new Client([
            'timeout' => 2,
        ]));
    }

    /**
     * @param string $hash
     *
     * @return string
     */
    private function getSecretSantaSessionKey($hash)
    {
        return sprintf('secret-santa-%s', $hash);
    }

    /**
     * @param Request $request
     * @param string  $hash
     *
     * @return SecretSanta
     */
    private function getSecretSantaOrThrow404(Request $request, $hash)
    {
        $secretSanta = $request->getSession()->get(
            $this->getSecretSantaSessionKey(
                $hash
            )
        );

        if (!$secretSanta) {
            throw new NotFoundHttpException();
        }

        return $secretSanta;
    }

    /**
     * @param string[] $selectedUsers
     * @param string   $message
     *
     * @return array
     */
    private function validate($selectedUsers, $message)
    {
        $errors = [];

        if (count($selectedUsers) < 2) {
            $errors['users'][] = 'At least 2 users should be selected';
        }

        return $errors;
    }
}
