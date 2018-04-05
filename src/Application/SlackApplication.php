<?php

/*
 * This file is part of the Slack Secret Santa project.
 *
 * (c) JoliCode <coucou@jolicode.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Joli\SlackSecretSanta\Application;

use Joli\SlackSecretSanta\SecretSanta;
use Joli\SlackSecretSanta\Slack\SecretDispatcher;
use Joli\SlackSecretSanta\Slack\UserExtractor;
use Joli\SlackSecretSanta\User;
use League\OAuth2\Client\Token\AccessToken;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\Session\SessionInterface;

class SlackApplication implements ApplicationInterface
{
    const SESSION_KEY_STATE = 'santa.slack.state';

    private const SESSION_KEY_TOKEN = 'santa.slack.token';
    private const SESSION_KEY_ADMIN = 'santa.slack.admin';

    private $requestStack;
    private $userExtractor;
    private $secretDispatcher;

    public function __construct(RequestStack $requestStack, UserExtractor $userExtractor, SecretDispatcher $secretDispatcher)
    {
        $this->requestStack = $requestStack;
        $this->userExtractor = $userExtractor;
        $this->secretDispatcher = $secretDispatcher;
    }

    public function getCode(): string
    {
        return 'slack';
    }

    public function isAuthenticated(): bool
    {
        try {
            $this->getToken();

            return true;
        } catch (\LogicException $e) {
            return false;
        }
    }

    public function getAuthenticationRoute(): string
    {
        return 'slack_authenticate';
    }

    public function getAdmin(): ?User
    {
        return $this->getSession()->get(self::SESSION_KEY_ADMIN);
    }

    public function setAdmin(User $admin): void
    {
        $this->getSession()->set(self::SESSION_KEY_ADMIN, $admin);
    }

    public function getUsers(): array
    {
        return $this->userExtractor->extractAll($this->getToken()->getToken());
    }

    public function sendRemainingMessages(SecretSanta $secretSanta): void
    {
        $this->secretDispatcher->dispatchRemainingMessages($secretSanta, $this->getToken()->getToken());
    }

    public function setToken(AccessToken $token)
    {
        $this->getSession()->set(self::SESSION_KEY_TOKEN, $token);
    }

    private function getToken(): AccessToken
    {
        $token = $this->getSession()->get(self::SESSION_KEY_TOKEN);

        if (!($token instanceof AccessToken)) {
            throw new \LogicException('Invalid token');
        }

        return $token;
    }

    private function getSession(): SessionInterface
    {
        return $this->requestStack->getMasterRequest()->getSession();
    }
}
