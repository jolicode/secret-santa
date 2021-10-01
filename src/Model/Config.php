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

class Config
{
    /**
     * @param User[]               $availableUsers
     * @param string[]             $selectedUsers
     * @param array<int, string>   $notes
     * @param array<string, mixed> $options
     */
    public function __construct(
        private array $availableUsers,
        private array $selectedUsers = [],
        private ?string $message = '',
        private array $notes = [],
        private array $options = [],
    ) {
    }

    /**
     * @return string[]
     */
    public function getSelectedUsers(): array
    {
        return $this->selectedUsers;
    }

    /**
     * @param string[] $selectedUsers
     */
    public function setSelectedUsers(array $selectedUsers): void
    {
        $this->selectedUsers = $selectedUsers;
    }

    /**
     * @return User[]
     */
    public function getAvailableUsers(): array
    {
        return $this->availableUsers;
    }

    /**
     * @param User[] $availableUsers
     */
    public function setAvailableUsers(array $availableUsers): void
    {
        $this->availableUsers = $availableUsers;
    }

    public function getMessage(): ?string
    {
        return $this->message;
    }

    public function setMessage(?string $message): void
    {
        $this->message = $message;
    }

    /**
     * @return string[]
     */
    public function getNotes(): array
    {
        return $this->notes;
    }

    /**
     * @param string[] $notes
     */
    public function setNotes(array $notes): void
    {
        $this->notes = $notes;
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
}
