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
    /** @var User[] */
    private array $availableUsers = [];
    /** @var Group[] */
    private array $groups = [];
    /** @var string[] */
    private array $selectedUsers = [];
    private ?string $message = '';
    /** @var array<int, string> */
    private array $notes = [];
    /** @var array<string, mixed> */
    private array $options = [];

    public function __construct(
        private string $application,
        private string $organization,
        private ?User $admin,
    ) {
    }

    public function getApplication(): ?string
    {
        return $this->application;
    }

    public function getOrganization(): ?string
    {
        return $this->organization;
    }

    public function getAdmin(): ?User
    {
        return $this->admin;
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

    /**
     * @param array<Group> $groups
     */
    public function setGroups(array $groups): void
    {
        $this->groups = $groups;
    }

    /**
     * @return Group[]
     */
    public function getGroups(): array
    {
        return $this->groups;
    }
}
