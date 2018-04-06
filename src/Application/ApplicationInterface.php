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
use Joli\SlackSecretSanta\User;

interface ApplicationInterface
{
    public function getCode(): string;

    public function isAuthenticated(): bool;

    public function getAuthenticationRoute(): string;

    public function getOrganization(): string;

    public function getAdmin(): ?User;

    /**
     * An array of User indexed by their identifier.
     *
     * @return User[]
     */
    public function getUsers(): array;

    public function sendRemainingMessages(SecretSanta $secretSanta): void;
}
