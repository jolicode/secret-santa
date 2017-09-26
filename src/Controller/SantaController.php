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
use Joli\SlackSecretSanta\Rudolph;
use Joli\SlackSecretSanta\SecretDispatcher;
use Joli\SlackSecretSanta\SecretSanta;
use Joli\SlackSecretSanta\Spoiler;
use Joli\SlackSecretSanta\UserExtractor;
use League\OAuth2\Client\Token\AccessToken;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\Session\SessionInterface;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Routing\RouterInterface;

class SantaController extends AbstractController
{
    const STATE_SESSION_KEY = 'santa.slack.state';
    const TOKEN_SESSION_KEY = 'santa.slack.token';
    const USER_ID_SESSION_KEY = 'santa.slack.user_id';

    private $slackClientId;
    private $slackClientSecret;
    private $session;
    private $router;
    private $twig;

    public function __construct(string $slackClientId, string $slackClientSecret, SessionInterface $session, RouterInterface $router, \Twig_Environment $twig)
    {
        $this->session = $session;
        $this->router = $router;
        $this->slackClientId = $slackClientId;
        $this->slackClientSecret = $slackClientSecret;
        $this->twig = $twig;
    }

    public function run(Request $request, SecretDispatcher $secretDispatcher, UserExtractor $userExtractor, Rudolph $rudolph): Response
    {
        $token = $this->session->get(self::TOKEN_SESSION_KEY);
        $userId = $this->session->get(self::USER_ID_SESSION_KEY);

        if (!($token instanceof AccessToken)) {
            return new RedirectResponse($this->router->generate('authenticate'));
        }

        $selectedUsers = [];
        $message = null;
        $errors = [];

        if ($request->isMethod('POST')) {
            $selectedUsers = $request->request->get('users');
            $message = $request->request->get('message');

            $errors = $this->validate($selectedUsers, $message);

            if (count($errors) < 1) {
                $associatedUsers = $rudolph->associateUsers($selectedUsers);
                $hash = md5(serialize($associatedUsers));

                $secretSanta = new SecretSanta($hash, $associatedUsers, $userId, str_replace('```', '', $message));

                $secretDispatcher->dispatchRemainingMessages($secretSanta, $token->getToken());

                $request->getSession()->set(
                    $this->getSecretSantaSessionKey(
                        $secretSanta->getHash()
                    ), $secretSanta
                );

                return new RedirectResponse($this->router->generate('finish', ['hash' => $secretSanta->getHash()]));
            }
        }

        try {
            $users = $userExtractor->extractAll($token->getToken());
            $content = $this->twig->render('santa/run.html.twig', [
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

    public function finish(Request $request, string $hash): Response
    {
        $secretSanta = $this->getSecretSantaOrThrow404($request, $hash);

        $content = $this->twig->render('santa/finish.html.twig', [
            'secretSanta' => $secretSanta,
        ]);

        return new Response($content);
    }

    public function spoil(Request $request, Spoiler $spoiler): Response
    {
        $code = $request->request->get('code');
        $invalidCode = false;
        $associations = null;

        if ($code) {
            $associations = $spoiler->decode($code);

            if (null === $associations) {
                $invalidCode = true;
            }
        }

        $content = $this->twig->render('santa/spoil.html.twig', [
            'code' => $code,
            'invalidCode' => $invalidCode,
            'associations' => $associations,
        ]);

        return new Response($content);
    }

    public function retry(Request $request, string $hash, SecretDispatcher $secretDispatcher): Response
    {
        $secretSanta = $this->getSecretSantaOrThrow404($request, $hash);

        $token = $this->session->get(self::TOKEN_SESSION_KEY);

        if (!($token instanceof AccessToken)) {
            return new RedirectResponse($this->router->generate('authenticate'));
        }

        $secretDispatcher->dispatchRemainingMessages($secretSanta, $token->getToken());

        $request->getSession()->set(
            $this->getSecretSantaSessionKey(
                $secretSanta->getHash()
            ), $secretSanta
        );

        return new RedirectResponse($this->router->generate('finish', ['hash' => $secretSanta->getHash()]));
    }

    /**
     * Ask for Slack authentication and store the AccessToken in Session.
     */
    public function authenticate(Request $request, ApiClient $apiClient): Response
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
        $response = $apiClient->send($test, $token->getToken());

        if ($response->isOk()) {
            $this->session->set(self::TOKEN_SESSION_KEY, $token);
            $this->session->set(self::USER_ID_SESSION_KEY, $response->getUserId());

            return new RedirectResponse($this->router->generate('run'));
        }

        return new RedirectResponse($this->router->generate('homepage'));
    }

    private function getSecretSantaSessionKey(string $hash): string
    {
        return sprintf('secret-santa-%s', $hash);
    }

    private function getSecretSantaOrThrow404(Request $request, string $hash): SecretSanta
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

    private function validate(array $selectedUsers, string $message): array
    {
        $errors = [];

        if (count($selectedUsers) < 2) {
            $errors['users'][] = 'At least 2 users should be selected';
        }

        return $errors;
    }
}
