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
use JoliCode\SecretSanta\Model\Config;
use JoliCode\SecretSanta\Model\Group;
use JoliCode\SecretSanta\Model\SecretSanta;
use JoliCode\SecretSanta\Model\User;
use Symfony\Component\Form\FormBuilderInterface;

interface ApplicationInterface
{
    public function getCode(): string;

    public function isAuthenticated(): bool;

    public function getAuthenticationRoute(): string;

    public function getOrganization(): string;

    public function getAdmin(): ?User;

    /**
     * An array of Group indexed by their identifier.
     *
     * @return Group[]
     */
    public function getGroups(): array;

    /**
     * An array of User indexed by their identifier.
     *
     * @return array<User>
     *
     * @throws UserExtractionFailedException
     */
    public function loadNextBatchOfUsers(Config $config): array;

    /**
     * @throws MessageSendFailedException
     */
    public function sendSecretMessage(SecretSanta $secretSanta, string $giver, string $receiver, bool $isSample = false): void;

    /**
     * @throws MessageSendFailedException
     */
    public function sendAdminMessage(SecretSanta $secretSanta, string $code, string $spoilUrl): void;

    public function configureMessageForm(FormBuilderInterface $builder): void;

    public function reset(): void;
}
