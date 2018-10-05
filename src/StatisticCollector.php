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

use Predis\Client;

class StatisticCollector
{
    private $client;

    public function __construct(Client $client)
    {
        $this->client = $client;
    }

    public function incrementUsageCount($clientApplication)
    {
        $currentYear = date('Y');
        $currentMonth = date('m');

        //If the key does not exist, it is set to 0 before performing the operation
        $this->client->hincrby("date:$currentYear-$currentMonth", 'usageCount', 1);
        $this->client->hincrby("date:$currentYear", 'usageCount', 1);
        $this->client->hincrby('date:total', 'usageCount', 1);
        $this->client->hincrby("date:total-$clientApplication", 'usageCount', 1);
    }

    public function getCounters()
    {
        $datesAndCounter = [];
        $megaHashes = [];

        $allHashes = [];
        $allHashes['hashesTotal'] = $this->client->keys('date:total*');
        $allHashes['hashesYear'] = $this->client->keys('date:????');
        $allHashes['hashesMonth'] = $this->client->keys('date:????-??');

        foreach ($allHashes as $key => $hashes) {
            foreach ($hashes as $hash) {
                $datesAndCounter[$hash] = $this->client->hget($hash, 'usageCount');
            }

            ksort($datesAndCounter);
            $megaHashes[$key] = $datesAndCounter;
            $datesAndCounter = [];
        }

        return $megaHashes;
    }
}
