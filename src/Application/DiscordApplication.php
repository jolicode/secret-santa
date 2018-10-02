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

use JoliCode\SecretSanta\Discord\ApiHelper;
use JoliCode\SecretSanta\Discord\MessageSender;
use JoliCode\SecretSanta\Discord\UserExtractor;
use JoliCode\SecretSanta\SecretSanta;
use JoliCode\SecretSanta\Statistic;
use JoliCode\SecretSanta\User;
use League\OAuth2\Client\Token\AccessToken;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\Session\SessionInterface;

class DiscordApplication implements ApplicationInterface
{
    const SESSION_KEY_STATE = 'santa.discord.state';

    private const SESSION_KEY_TOKEN = 'santa.discord.token';
    private const SESSION_KEY_ADMIN = 'santa.discord.admin';
    private const SESSION_KEY_GUILD_ID = 'santa.discord.guild_id';

    private $requestStack;
    private $apiHelper;
    private $userExtractor;
    private $messageSender;
    /**
     * @var Statistic
     */
    private $statistic;

    public function __construct(RequestStack $requestStack, ApiHelper $apiHelper, UserExtractor $userExtractor, MessageSender $messageSender, Statistic $statistic)
    {
        $this->requestStack = $requestStack;
        $this->apiHelper = $apiHelper;
        $this->userExtractor = $userExtractor;
        $this->messageSender = $messageSender;
        $this->statistic = $statistic;
    }

    public function getCode(): string
    {
        return 'discord';
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
        return 'discord_authenticate';
    }

    public function getOrganization(): string
    {
        return $this->apiHelper->getGuild($this->getGuildId())->name;
    }

    public function getAdmin(): ?User
    {
        return $this->getSession()->get(self::SESSION_KEY_ADMIN);
    }

    public function setAdmin(User $admin): void
    {
        $this->getSession()->set(self::SESSION_KEY_ADMIN, $admin);
    }

    public function getGuildId(): ?string
    {
        return $this->getSession()->get(self::SESSION_KEY_GUILD_ID);
    }

    public function setGuildId(string $guild): void
    {
        $this->getSession()->set(self::SESSION_KEY_GUILD_ID, $guild);
    }

    public function getUsers(): array
    {
        $guildId = $this->getGuildId();

        if (!$guildId) {
            throw new \RuntimeException('No guild was selected');
        }

        return $this->userExtractor->extractForGuild($guildId);
    }

    public function sendSecretMessage(SecretSanta $secretSanta, string $giver, string $receiver): void
    {
        $this->messageSender->sendSecretMessage($secretSanta, $giver, $receiver);
    }

    public function sendAdminMessage(SecretSanta $secretSanta, string $code, string $spoilUrl): void
    {
        $this->messageSender->sendAdminMessage($secretSanta, $code, $spoilUrl);
    }

    public function finish(SecretSanta $secretSanta)
    {
        $this->statistic->incrementUsageCount();

        $this->getSession()->remove(self::SESSION_KEY_TOKEN);
        $this->getSession()->remove(self::SESSION_KEY_ADMIN);
        $this->getSession()->remove(self::SESSION_KEY_GUILD_ID);
    }

    public function setToken(AccessToken $token)
    {
        $this->getSession()->set(self::SESSION_KEY_TOKEN, $token);
    }

    public function getToken(): AccessToken
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
