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
use JoliCode\SecretSanta\Model\Group;
use JoliCode\SecretSanta\Model\SecretSanta;
use JoliCode\SecretSanta\Model\User;
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

    /** @var Group[]|null */
    private $groups = null;

    public function __construct(RequestStack $requestStack, ApiHelper $apiHelper, UserExtractor $userExtractor, MessageSender $messageSender)
    {
        $this->requestStack = $requestStack;
        $this->apiHelper = $apiHelper;
        $this->userExtractor = $userExtractor;
        $this->messageSender = $messageSender;
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

    public function getGroups(): array
    {
        $this->loadGroups();

        return $this->groups;
    }

    public function getUsers(): array
    {
        $guildId = $this->getGuildId();

        if (!$guildId) {
            throw new \RuntimeException('No guild was selected.');
        }

        $users = $this->userExtractor->extractForGuild($guildId);

        $this->loadGroups();

        if ($this->groups) {
            // Store relation User <-> Group in Group model
            foreach ($users as $user) {
                $userGroupIds = $user->getExtra()['groups'] ?? [];

                foreach ($userGroupIds as $userGroupId) {
                    if (isset($this->groups[$userGroupId])) {
                        $this->groups[$userGroupId]->addUser($user->getIdentifier());
                    }
                }
            }
        }

        return $users;
    }

    public function sendSecretMessage(SecretSanta $secretSanta, string $giver, string $receiver, bool $isSample = false): void
    {
        $this->messageSender->sendSecretMessage($secretSanta, $giver, $receiver, $isSample);
    }

    public function sendAdminMessage(SecretSanta $secretSanta, string $code, string $spoilUrl): void
    {
        $this->messageSender->sendAdminMessage($secretSanta, $code, $spoilUrl);
    }

    public function finish(SecretSanta $secretSanta)
    {
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
            throw new \LogicException('Invalid token.');
        }

        return $token;
    }

    private function loadGroups()
    {
        // Store groups in memory because we need it in multiple places.
        if (null === $this->groups) {
            $guildId = $this->getGuildId();

            if (!$guildId) {
                throw new \RuntimeException('No guild was selected.');
            }

            $this->groups = $this->userExtractor->extractGroupsForGuild($guildId);
        }
    }

    private function getSession(): SessionInterface
    {
        return $this->requestStack->getMasterRequest()->getSession();
    }
}
