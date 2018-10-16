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

    public function incrementUsageCount(string $applicationCode)
    {
        $currentYear = date('Y');
        $currentMonth = date('m');

        // If the key does not exist, it is set to 0 before performing the operation
        $this->client->incr("date:$currentYear-$currentMonth");
        $this->client->incr("date:$currentYear");
        $this->client->incr('date:total');
        $this->client->incr("date:total-$applicationCode");
    }

    public function getDateAndCounters(): array
    {
        $datesAndCounter = [
            'month' => [],
            'year' => [],
            'total' => [],
        ];

        $allKeys = $this->client->keys('date:*');

        if (\count($allKeys) > 0) {
            $allStatistics = $this->client->mget($allKeys);

            foreach ($allKeys as $key => $date) {
                if (preg_match('/date:\d\d\d\d-\d\d/', $date)) {
                    $datesAndCounter['month'][$date] = $allStatistics[$key];
                } elseif (preg_match('/date:\d\d\d\d/', $date)) {
                    $datesAndCounter['year'][$date] = $allStatistics[$key];
                } elseif (preg_match('/date:total/', $date)) {
                    $datesAndCounter['total'][$date] = $allStatistics[$key];
                }
            }

            ksort($datesAndCounter['month']);
            ksort($datesAndCounter['year']);
            ksort($datesAndCounter['total']);
        }

        return $datesAndCounter;
    }
}
