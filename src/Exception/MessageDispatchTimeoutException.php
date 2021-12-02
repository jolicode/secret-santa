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

class MessageDispatchTimeoutException extends \RuntimeException implements SecretSantaException
{
    public function __construct(private SecretSanta $secretSanta)
    {
        parent::__construct('It takes too much time to send messages!');
    }

    public function getSecretSanta(): SecretSanta
    {
        return $this->secretSanta;
    }
}
