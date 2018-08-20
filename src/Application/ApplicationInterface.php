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

use JoliCode\SecretSanta\Exception\MessageSendFailedException;
use JoliCode\SecretSanta\Exception\UserExtractionFailedException;
use JoliCode\SecretSanta\SecretSanta;
use JoliCode\SecretSanta\User;

interface ApplicationInterface
{
    public function getCode(): string;

    public function isAuthenticated(): bool;

    public function getStartRoute(): string;

    public function getOrganization(): string;

    public function getAdmin(): ?User;

    /**
     * An array of User indexed by their identifier.
     *
     * @throws UserExtractionFailedException
     *
     * @return User[]
     */
    public function getUsers(): array;

    /**
     * @throws MessageSendFailedException
     */
    public function sendSecretMessage(SecretSanta $secretSanta, string $giver, string $receiver): void;

    /**
     * @throws MessageSendFailedException
     */
    public function sendAdminMessage(SecretSanta $secretSanta, string $code, string $spoilUrl): void;

    public function finish(SecretSanta $secretSanta);
}
