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

        $associations = $santa->getAssociations();
        $firstGiver = array_key_first($associations);
        $currentGiver = $firstGiver;

        // Sort givers array to have a consistent order and reduce spoiler size by using a chain
        // user1 offers to user2 who offers to user3 who offers to ... who offers to user1
        while ($currentGiver) {
            $giverUser = $santa->getUser($currentGiver);
            $givers[] = $giverUser->getName() ?: $giverUser->getIdentifier();
            $currentGiver = $associations[$currentGiver] ?? null;
            if ($currentGiver === $firstGiver) {
                break;
            }
        }

        return 'v3@' . base64_encode(gzencode(json_encode($givers)));
    }

    /**
     * @return array<string, string>|null
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
     * @return array<string, string>|null
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
     * @return array<string, string>|null
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
     * @return array<string, string>|null
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
