<?php

/*
 * This file is part of the Secret Santa project.
 *
 * (c) JoliCode <coucou@jolicode.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace JoliCode\SecretSanta;

class Spoiler
{
    public function encode(SecretSanta $santa): string
    {
        $associations = [];

        foreach ($santa->getAssociations() as $giver => $receiver) {
            $giverUser = $santa->getUser($giver);
            $receiverUser = $santa->getUser($receiver);
            $associations[$giverUser->getName() ?: $giverUser->getIdentifier()] = $receiverUser->getName() ?: $receiverUser->getIdentifier();
        }

        return 'v2@' . base64_encode(json_encode($associations));
    }

    public function decode(string $string): ?array
    {
        $string = trim($string);

        $version = substr($string, 0, strpos($string, '@'));
        $encoded = substr($string, \strlen($version) + 1);

        if ('v1' === $version) {
            return $this->decodeV1($encoded);
        }

        if ('v2' === $version) {
            return $this->decodeV2($encoded);
        }

        return null;
    }

    private function decodeV1(string $encoded): ?array
    {
        $v2Decoded = $this->decodeV2($encoded);

        if (!$v2Decoded) {
            return null;
        }

        $associations = [];

        foreach ($v2Decoded as $giver => $receiver) {
            $associations['@' . $giver] = '@' . $receiver;
        }

        return $associations;
    }

    private function decodeV2(string $encoded): ?array
    {
        $base64Decoded = base64_decode($encoded, true);

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
