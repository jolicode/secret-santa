<?php

/*
 * This file is part of the Secret Santa project.
 *
 * (c) JoliCode <coucou@jolicode.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace JoliCode\SecretSanta\Santa;

use JoliCode\SecretSanta\Model\SecretSanta;

class Spoiler
{
    public function encode(SecretSanta $santa): string
    {
        $givers = [];

        foreach ($santa->getAssociations() as $giver => $receiver) {
            $giverUser = $santa->getUser($giver);
            $givers[] = $giverUser->getName() ?: $giverUser->getIdentifier();
        }

        return 'v3@' . base64_encode(gzencode(json_encode($givers)));
    }

    /**
     * @return null|array<string, string>
     */
    public function decode(string $string): ?array
    {
        $string = trim($string);

        $version = substr($string, 0, strpos($string, '@'));
        $encoded = substr($string, \strlen($version) + 1);

        return match ($version) {
            'v1' => $this->decodeV1($encoded),
            'v2' => $this->decodeV2($encoded),
            'v3' => $this->decodeV3($encoded),
            default => null,
        };
    }

    /**
     * @return null|array<string, string>
     */
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

    /**
     * @return null|array<string, string>
     */
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

    /**
     * @return null|array<string, string>
     */
    private function decodeV3(string $encoded): ?array
    {
        $base64Decoded = base64_decode($encoded, true);

        if (!$base64Decoded) {
            return null;
        }

        $gzDecoded = gzdecode($base64Decoded);

        if (!$gzDecoded) {
            return null;
        }

        $jsonDecoded = json_decode($gzDecoded, true);

        if (!$jsonDecoded) {
            return null;
        }

        $count = \count($jsonDecoded);
        $associations = [];

        for ($i = 0; $i < $count - 1; ++$i) {
            $associations[$jsonDecoded[$i]] = $jsonDecoded[$i + 1];
        }
        $associations[$jsonDecoded[$count - 1]] = $jsonDecoded[0];

        return $associations;
    }
}
