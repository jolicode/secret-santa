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
    private $hash;

    /** @var array */
    private $associations;

    /** @var array */
    private $remainingAssociations;

    /** @var string|null */
    private $adminUserId;

    /** @var string|null */
    private $adminMessage;

    /** @var string[] */
    private $errors = [];

    public function __construct(string $hash, array $associations, ?string $adminUserId, ?string $adminMessage)
    {
        $this->hash = $hash;
        $this->associations = $associations;
        $this->remainingAssociations = $associations;
        $this->adminUserId = $adminUserId;
        $this->adminMessage = $adminMessage;
    }

    public function getHash(): ?string
    {
        return $this->hash;
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

    public function getAdminUserId(): ?string
    {
        return $this->adminUserId;
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
