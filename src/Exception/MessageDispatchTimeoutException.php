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

use JoliCode\SecretSanta\SecretSanta;

class MessageDispatchTimeoutException extends \RuntimeException implements SecretSantaException
{
    private $secretSanta;

    public function __construct(SecretSanta $secretSanta)
    {
        $this->secretSanta = $secretSanta;

        parent::__construct('It takes too much time to send messages!');
    }

    public function getSecretSanta(): SecretSanta
    {
        return $this->secretSanta;
    }
}
