<?php

/*
 * This file is part of the Secret Santa project.
 *
 * (c) JoliCode <coucou@jolicode.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace JoliCode\SecretSanta\Exception;

use JoliCode\SecretSanta\Model\SecretSanta;
use JoliCode\SecretSanta\Model\User;

class MessageSendFailedException extends \RuntimeException implements SecretSantaException
{
    public function __construct(
        private SecretSanta $secretSanta,
        private User $recipient,
        \Throwable $previous = null,
        string $precision = null,
    ) {
        parent::__construct(sprintf('Fail to send message to @%s.%s', $recipient->getName(), $precision ? ' ' . $precision : ''), 0, $previous);
    }

    public function getSecretSanta(): SecretSanta
    {
        return $this->secretSanta;
    }

    public function getRecipient(): User
    {
        return $this->recipient;
    }
}
