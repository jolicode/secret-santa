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

class Group
{
    private $identifier;
    private $name;
    /** @var string[] */
    private $userIds = [];

    public function __construct(string $identifier, string $name)
    {
        $this->identifier = $identifier;
        $this->name = $name;
    }

    public function getIdentifier(): string
    {
        return $this->identifier;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function addUser(string $userId): void
    {
        $this->userIds[] = $userId;
    }

    /**
     * @return string[]
     */
    public function getUserIds(): array
    {
        return $this->userIds;
    }
}
