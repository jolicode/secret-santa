<?php

/*
 * This file is part of the Secret Santa project.
 *
 * (c) JoliCode <coucou@jolicode.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace JoliCode\SecretSanta\Application;

use JoliCode\SecretSanta\SecretSanta;
use JoliCode\SecretSanta\Slack\MessageSender;
use JoliCode\SecretSanta\Slack\UserExtractor;
use JoliCode\SecretSanta\User;
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
    private $messageSender;

    public function __construct(RequestStack $requestStack, UserExtractor $userExtractor, MessageSender $messageSender)
    {
        $this->requestStack = $requestStack;
        $this->userExtractor = $userExtractor;
        $this->messageSender = $messageSender;
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

    public function getOrganization(): string
    {
        return $this->getToken()->getValues()['team_name'] ?? '';
    }

    public function getAdmin(): ?User
    {
        return $this->getSession()->get(self::SESSION_KEY_ADMIN);
    }

    public function setAdmin(User $admin): void
    {
        $this->getSession()->set(self::SESSION_KEY_ADMIN, $admin);
    }

    public function getGroups(): array
    {
        return $this->userExtractor->extractGroups($this->getToken()->getToken());
    }

    public function getUsers(): array
    {
        return $this->userExtractor->extractAll($this->getToken()->getToken());
    }

    public function sendSecretMessage(SecretSanta $secretSanta, string $giver, string $receiver, bool $isSample = false): void
    {
        $this->messageSender->sendSecretMessage($secretSanta, $giver, $receiver, $this->getToken()->getToken(), $isSample);
    }

    public function sendAdminMessage(SecretSanta $secretSanta, string $code, string $spoilUrl): void
    {
        $this->messageSender->sendAdminMessage($secretSanta, $code, $spoilUrl, $this->getToken()->getToken());
    }

    public function finish(SecretSanta $secretSanta)
    {
        $this->getSession()->remove(self::SESSION_KEY_TOKEN);
        $this->getSession()->remove(self::SESSION_KEY_ADMIN);
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
