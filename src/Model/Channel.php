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

final readonly class Channel
{
    public function __construct(
        public string $id,
        public string $name,
        /** Whether the channel is shared with other workspaces (Slack Connect) */
        public bool $isShared,
        /** Whether the bot is a member of the channel */
        public bool $isJoined,
    ) {
    }

    /**
     * The bot can only message the external members of a shared channel once it has been invited in it.
     */
    public function needsInvitation(): bool
    {
        return $this->isShared && !$this->isJoined;
    }
}
