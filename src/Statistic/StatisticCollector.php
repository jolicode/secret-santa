<?php

/*
 * This file is part of the Secret Santa project.
 *
 * (c) JoliCode <coucou@jolicode.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace JoliCode\SecretSanta\Statistic;

use JoliCode\SecretSanta\Model\SecretSanta;
use Predis\Client;

class StatisticCollector
{
    private $client;

    public function __construct(Client $client)
    {
        $this->client = $client;
    }

    public function incrementUsageCount(SecretSanta $secretSanta)
    {
        $applicationCode = $secretSanta->getApplication();
        $currentYear = date('Y');
        $currentMonth = date('m');

        // If the key does not exist, it is set to 0 before performing the operation
        $this->client->incr("stats:month:$currentYear-$currentMonth");
        $this->client->incr("stats:month-app:$currentYear-$currentMonth-$applicationCode");
        $this->client->incr("stats:year:$currentYear");
        $this->client->incr("stats:year-app:$currentYear-$applicationCode");
        $this->client->incr("stats:app:$applicationCode");
        $this->client->incr('stats:total');
        $this->client->incrby('stats:users', \count($secretSanta->getUsers()));

        $usersMax = (int) $this->client->get('stats:users-max');
        if (\count($secretSanta->getUsers()) > $usersMax) {
            $this->client->set('stats:users-max', \count($secretSanta->getUsers()));
        }
    }

    public function incrementSampleCount(SecretSanta $secretSanta)
    {
        $this->client->incr('stats:sample');
    }

    public function incrementSpoilCount()
    {
        $this->client->incr('stats:spoil');
    }

    public function getCounters(): array
    {
        $counters = [
            'month' => [],
            'month-app' => [],
            'year' => [],
            'year-app' => [],
            'app' => [],
            'total' => 0,
            'users' => 0,
            'users-max' => 0,
            'sample' => 0,
            'spoil' => 0,
        ];

        $keys = $this->client->keys('stats:*');
        if (\count($keys) > 0) {
            $stats = $this->client->mget($keys);

            foreach ($keys as $keyIndex => $key) {
                if (preg_match('/^stats:(?<type>.*):(?<key>.*)$/', $key, $matches)) {
                    $counters[$matches['type']][$matches['key']] = $stats[$keyIndex];
                } else {
                    // simple counter
                    $counters[str_replace('stats:', '', $key)] = $stats[$keyIndex];
                }
            }

            ksort($counters['month']);
            ksort($counters['year']);
        }

        foreach (['year', 'month'] as $stat) {
            $counter = [];
            foreach ($counters[$stat] as $key => $value) {
                $counter[$key] = [
                    'total' => $value,
                    'applications' => [],
                ];
                foreach ($counters['app'] as $applicationCode => $applicationTotal) {
                    if ('year' === $stat && 2019 === $key) {
                        $counter[$key]['applications'][$applicationCode] = 0;
                    } else {
                        $counter[$key]['applications'][$applicationCode] =
                            !empty($counters["$stat-app"])
                            && !empty($counters["$stat-app"]["$key-$applicationCode"])
                                ? $counters["$stat-app"]["$key-$applicationCode"]
                                : 0;
                    }
                }
            }

            unset($counters["$stat-app"]);

            $counters[$stat] = $counter;

            if (\count($counter) > 0) {
                $counters["$stat-max"] = max(array_column($counter, 'total'));
            }
        }

        return $counters;
    }
}
