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

interface SecretSantaException
{
    public function getSecretSanta(): SecretSanta;
}
