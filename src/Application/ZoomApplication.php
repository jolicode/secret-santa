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

use JoliCode\SecretSanta\Model\Group;
use JoliCode\SecretSanta\Model\SecretSanta;
use JoliCode\SecretSanta\Model\User;
use League\OAuth2\Client\Token\AccessToken;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\Session\SessionInterface;

class ZoomApplication implements ApplicationInterface
{
    const APPLICATION_CODE = 'zoom';

    const SESSION_KEY_STATE = 'santa.zoom.state';

    private const SESSION_KEY_TOKEN = 'santa.zoom.token';
    private const SESSION_KEY_ADMIN = 'santa.zoom.admin';
    //private const SESSION_KEY_GUILD_ID = 'santa.zoom.guild_id';

    private $requestStack;
    private $apiHelper;
    private $userExtractor;
    private $messageSender;

    /** @var Group[]|null */
    private $groups = null;

    public function __construct(RequestStack $requestStack)
    {
        $this->requestStack = $requestStack;
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
        return 'todo zoom name';
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


        return [];
    }

    public function sendSecretMessage(SecretSanta $secretSanta, string $giver, string $receiver, bool $isSample = false): void
    {
        //$this->messageSender->sendSecretMessage($secretSanta, $giver, $receiver, $isSample);
    }

    public function sendAdminMessage(SecretSanta $secretSanta, string $code, string $spoilUrl): void
    {
        //$this->messageSender->sendAdminMessage($secretSanta, $code, $spoilUrl);
    }

    public function reset()
    {
        $this->getSession()->remove(self::SESSION_KEY_TOKEN);
        $this->getSession()->remove(self::SESSION_KEY_ADMIN);
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

    private function getSession(): SessionInterface
    {
        return $this->requestStack->getMasterRequest()->getSession();
    }
}
