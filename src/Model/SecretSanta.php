<?php

/*
 * This file is part of the Secret Santa project.
 *
 * (c) JoliCode <coucou@jolicode.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace JoliCode\SecretSanta\Model;

class SecretSanta
{
    /** @var array<string, string> */
    private array $remainingAssociations;

    /** @var string[] */
    private array $errors = [];

    /**
     * @param User[]                $users
     * @param array<string, string> $associations
     * @param array<int, string>    $notes
     * @param array<string, mixed>  $options
     */
    public function __construct(
        private string $application,
        private string $organization,
        private string $hash,
        private array $users,
        private array $associations,
        private ?User $admin,
        private ?string $adminMessage,
        private array $notes = [],
        private array $options = []
    ) {
        $this->remainingAssociations = $associations;
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

    public function getUserNote(string $identifier): string
    {
        return $this->notes[$identifier] ?? '';
    }

    /**
     * @return array<string, string>
     */
    public function getAssociations(): array
    {
        return $this->associations;
    }

    /**
     * @return array<string, string>
     */
    public function getRemainingAssociations(): array
    {
        return $this->remainingAssociations;
    }

    /**
     * @return string[]
     */
    public function getErrors(): array
    {
        return $this->errors;
    }

    /**
     * @return string[]
     */
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

    /**
     * @return array<string, mixed>
     */
    public function getOptions(): array
    {
        return $this->options;
    }

    /**
     * @param array<string, mixed> $options
     */
    public function setOptions(array $options): void
    {
        $this->options = $options;
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
