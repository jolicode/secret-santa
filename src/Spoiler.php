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

class Spoiler
{
    public function encode(SecretSanta $santa): string
    {
        $associations = $santa->getAssociations();

        return 'v1@' . base64_encode(json_encode($associations));
    }

    public function decode(string $encoded): ?array
    {
        $encoded = trim($encoded);
        $version = substr($encoded, 0, 2);

        if ($version !== 'v1') {
            return null;
        }

        $encodedAssociations = substr($encoded, 3);
        $base64Decoded = base64_decode($encodedAssociations, true);

        if (!$base64Decoded) {
            return null;
        }

        $jsonDecoded = json_decode($base64Decoded, true);

        if (!$jsonDecoded) {
            return null;
        }

        return $jsonDecoded;
    }
}
