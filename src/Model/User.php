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

class User
{
    private $identifier;
    private $name;
    private $extra;

    public function __construct(string $identifier, string $name, array $extra = [])
    {
        $this->identifier = $identifier;
        $this->name = $name;
        $this->extra = $extra;
    }

    public function getIdentifier(): string
    {
        return $this->identifier;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function getExtra(): array
    {
        return $this->extra;
    }
}
