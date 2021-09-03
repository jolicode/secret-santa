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

use JoliCode\SecretSanta\Model\SecretSanta;
use JoliCode\SecretSanta\Model\User;
use JoliCode\SecretSanta\Zoom\MessageSender;
use JoliCode\SecretSanta\Zoom\UserExtractor;
use League\OAuth2\Client\Token\AccessTokenInterface;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\Session\SessionInterface;

class ZoomApplication implements ApplicationInterface
{
    public const APPLICATION_CODE = 'zoom';
    public const SESSION_KEY_STATE = 'santa.zoom.state';

    private const SESSION_KEY_TOKEN = 'santa.zoom.token';
    private const SESSION_KEY_BOT_TOKEN = 'santa.zoom.bot_token';
    private const SESSION_KEY_ADMIN = 'santa.zoom.admin';
    private const SESSION_KEY_ACCOUNT_ID = 'santa.zoom.account_id';

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
        return self::APPLICATION_CODE;
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
        return 'zoom_authenticate';
    }

    public function getOrganization(): string
    {
        return $this->getAccountId(); // todo: there is no team name :/
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
        return [];
    }

    public function getUsers(): array
    {
        return $this->userExtractor->extractAll($this->getToken()->getToken());
    }

    public function sendSecretMessage(SecretSanta $secretSanta, string $giver, string $receiver, bool $isSample = false): void
    {
        $this->messageSender->sendSecretMessage($secretSanta, $giver, $receiver, $this->getBotToken()->getToken(), $this->getAccountId(), $isSample);
    }

    public function sendAdminMessage(SecretSanta $secretSanta, string $code, string $spoilUrl): void
    {
        $this->messageSender->sendAdminMessage($secretSanta, $code, $spoilUrl, $this->getBotToken()->getToken(), $this->getAccountId());
    }

    public function reset(): void
    {
        $this->getSession()->remove(self::SESSION_KEY_TOKEN);
        $this->getSession()->remove(self::SESSION_KEY_BOT_TOKEN);
        $this->getSession()->remove(self::SESSION_KEY_ACCOUNT_ID);
        $this->getSession()->remove(self::SESSION_KEY_ADMIN);
    }

    public function setAccountId(string $accountId): void
    {
        $this->getSession()->set(self::SESSION_KEY_ACCOUNT_ID, $accountId);
    }

    public function getAccountId(): string
    {
        return $this->getSession()->get(self::SESSION_KEY_ACCOUNT_ID);
    }

    public function setToken(AccessTokenInterface $token): void
    {
        $this->getSession()->set(self::SESSION_KEY_TOKEN, $token);
    }

    public function setBotToken(AccessTokenInterface $token): void
    {
        $this->getSession()->set(self::SESSION_KEY_BOT_TOKEN, $token);
    }

    public function getToken(): AccessTokenInterface
    {
        $token = $this->getSession()->get(self::SESSION_KEY_TOKEN);

        if (!$token instanceof AccessTokenInterface) {
            throw new \LogicException('Invalid token.');
        }

        return $token;
    }

    public function getBotToken(): AccessTokenInterface
    {
        $token = $this->getSession()->get(self::SESSION_KEY_BOT_TOKEN);
        if (!$token instanceof AccessTokenInterface) {
            throw new \LogicException('Invalid token.');
        }

        return $token;
    }

    private function getSession(): SessionInterface
    {
        return $this->requestStack->getMainRequest()->getSession();
    }
}
