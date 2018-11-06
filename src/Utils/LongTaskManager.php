<?php

/*
 * This file is part of the Secret Santa project.
 *
 * (c) JoliCode <coucou@jolicode.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace JoliCode\SecretSanta\Utils;

/**
 * Handle a repetitive task and limit its duration to 20 seconds to be able to
 * display nice error message instead of being timed out by hosting.
 */
class LongTaskManager
{
    const DEFAULT_TIMEOUT = 19;

    public function process(
        callable $iterate,
        callable $shouldContinue,
        callable $onTimeout,
        $initialValue = null,
        int $timeout = self::DEFAULT_TIMEOUT
    ): void {
        $value = $initialValue;
        $startTime = time();

        do {
            if ((time() - $startTime) > $timeout) {
                $onTimeout();

                return;
            }

            $value = $iterate($value);
        } while ($shouldContinue($value));
    }
}
