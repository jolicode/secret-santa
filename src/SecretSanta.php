<?php

/*
 * This file is part of the Slack Secret Santa project.
 *
 * (c) JoliCode <coucou@jolicode.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Joli\SlackSecretSanta;

class SecretSanta
{
    /** @var string */
    private $application;

    /** @var string */
    private $organization;

    /** @var string */
    private $hash;

    /** @var User[] */
    private $users;

    /** @var array */
    private $associations;

    /** @var array */
    private $remainingAssociations;

    /** @var User|null */
    private $admin;

    /** @var string|null */
    private $adminMessage;

    /** @var string[] */
    private $errors = [];

    public function __construct(
        string $application,
        string $organization,
        string $hash,
        array $users,
        array $associations,
        ?User $admin,
        ?string $adminMessage
    ) {
        $this->application = $application;
        $this->organization = $organization;
        $this->hash = $hash;
        $this->users = $users;
        $this->associations = $associations;
        $this->remainingAssociations = $associations;
        $this->admin = $admin;
        $this->adminMessage = $adminMessage;
    }

    public function getApplication(): ?string
    {
        return $this->application;
    }

    public function getOrganization(): ?string
    {
        return $this->organization;
    }

    public function getHash(): ?string
    {
        return $this->hash;
    }

    /**
     * @return User[]
     */
    public function getUsers(): array
    {
        return $this->users;
    }

    public function getUser(string $identifier): ?User
    {
        return $this->users[$identifier] ?? null;
    }

    public function getAssociations(): array
    {
        return $this->associations;
    }

    public function getRemainingAssociations(): array
    {
        return $this->remainingAssociations;
    }

    public function getErrors(): array
    {
        return $this->errors;
    }

    public function getUniqueErrors(): array
    {
        return array_unique($this->errors);
    }

    public function getAdmin(): ?User
    {
        return $this->admin;
    }

    public function getAdminMessage(): ?string
    {
        return $this->adminMessage;
    }

    public function isDone(): bool
    {
        return empty($this->remainingAssociations);
    }

    public function markAssociationAsProceeded(string $giver): void
    {
        unset($this->remainingAssociations[$giver]);
    }

    public function addError(string $error): void
    {
        $this->errors[] = $error;
    }
}
