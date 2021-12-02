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

    /** @param array<string, string> $associations */
    public function __construct(
        private string $application,
        private string $organization,
        private string $hash,
        private array $associations,
        private ?User $admin,
        private Config $config,
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

    public function getUserCount(): int
    {
        return \count($this->config->getSelectedUsers());
    }

    public function getUser(string $identifier): ?User
    {
        return $this->config->getAvailableUsers()[$identifier] ?? null;
    }

    public function getUserNote(string $identifier): string
    {
        return $this->config->getNotes()[$identifier] ?? '';
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

    public function addError(string $error, string $giver): void
    {
        $this->errors[$giver] = $error;
    }

    /**
     * @return string[]
     */
    public function getErrors(): array
    {
        return $this->errors;
    }

    public function resetErrors(): void
    {
        $this->errors = [];
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
        return $this->config->getMessage();
    }

    /**
     * @return array<string, mixed>
     */
    public function getOptions(): array
    {
        return $this->config->getOptions();
    }

    /**
     * @param array<string, mixed> $options
     */
    public function setOptions(array $options): void
    {
        $this->config->setOptions($options);
    }

    public function isDone(): bool
    {
        foreach ($this->errors as $user => $error) {
            if (\array_key_exists($user, $this->remainingAssociations)) {
                return false;
            }
        }

        return empty($this->remainingAssociations);
    }

    public function markAssociationAsProceeded(string $giver): void
    {
        unset($this->remainingAssociations[$giver]);
    }

    public function getConfig(): Config
    {
        return $this->config;
    }

    public function setConfig(Config $config): void
    {
        $this->config = $config;
    }
}
