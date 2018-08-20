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

use JoliCode\SecretSanta\Microsoft\Configuration;
use JoliCode\SecretSanta\Microsoft\MessageSender;
use JoliCode\SecretSanta\Microsoft\UserExtractor;
use JoliCode\SecretSanta\SecretSanta;
use JoliCode\SecretSanta\User;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\Session\SessionInterface;

class TeamsApplication implements ApplicationInterface
{
    private const SESSION_KEY_CONFIGURATION = 'santa.teams.configuration';

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
        return 'teams';
    }

    public function isAuthenticated(): bool
    {
        return null !== $this->getConfiguration();
    }

    public function getStartRoute(): string
    {
        return 'teams_start';
    }

    public function getOrganization(): string
    {
        return $this->getConfiguration()->getTeamName();
    }

    public function getAdmin(): ?User
    {
        return $this->getConfiguration()->getAdmin();
    }

    public function setConfiguration(Configuration $configuration)
    {
        $this->getSession()->set(self::SESSION_KEY_CONFIGURATION, $configuration);
    }

    public function getConfiguration(): ?Configuration
    {
        return $this->getSession()->get(self::SESSION_KEY_CONFIGURATION);
    }

    public function getUsers(): array
    {
        return $this->userExtractor->extractAll($this->getConfiguration());
    }

    public function sendSecretMessage(SecretSanta $secretSanta, string $giver, string $receiver): void
    {
        $this->messageSender->sendSecretMessage($secretSanta, $giver, $receiver, $this->getConfiguration());
    }

    public function sendAdminMessage(SecretSanta $secretSanta, string $code, string $spoilUrl): void
    {
        $this->messageSender->sendAdminMessage($secretSanta, $code, $spoilUrl, $this->getConfiguration());
    }

    public function finish(SecretSanta $secretSanta)
    {
        $this->getSession()->remove(self::SESSION_KEY_CONFIGURATION);
    }

    private function getSession(): SessionInterface
    {
        return $this->requestStack->getMasterRequest()->getSession();
    }
}
