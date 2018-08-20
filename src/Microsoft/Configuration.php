<?php

/*
 * This file is part of the Secret Santa project.
 *
 * (c) JoliCode <coucou@jolicode.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace JoliCode\SecretSanta\Microsoft;

use JoliCode\SecretSanta\User;

class Configuration
{
    private $admin;
    private $token;
    private $serviceUrl;
    private $teamId;
    private $teamName;
    private $tenantId;
    private $bot;

    public function __construct(
        User $admin,
        string $token,
        string $serviceUrl,
        string $teamId,
        string $teamName,
        string $tenantId,
        string $bot
    ) {
        $this->admin = $admin;
        $this->token = $token;
        $this->serviceUrl = $serviceUrl;
        $this->teamId = $teamId;
        $this->teamName = $teamName;
        $this->tenantId = $tenantId;
        $this->bot = $bot;
    }

    public function getAdmin(): User
    {
        return $this->admin;
    }

    public function getToken(): string
    {
        return $this->token;
    }

    public function getServiceUrl(): string
    {
        return $this->serviceUrl;
    }

    public function getTeamId(): string
    {
        return $this->teamId;
    }

    public function getTeamName(): string
    {
        return $this->teamName;
    }

    public function getTenantId(): string
    {
        return $this->tenantId;
    }

    public function getBot(): string
    {
        return $this->bot;
    }
}
