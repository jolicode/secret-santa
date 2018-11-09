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
abstract class LongTask
{
    const DEFAULT_TIMEOUT = 20;

    /**
     * @return mixed
     */
    public function run(int $timeout = self::DEFAULT_TIMEOUT)
    {
        $this->init();

        $value = $this->getInitialValue();
        $startTime = time();

        do {
            if ((time() - $startTime) > $timeout) {
                $this->onTimeOut();

                return;
            }

            $value = $this->iterate($value);
        } while ($this->shouldContinue($value));

        return $this->getResult();
    }

    protected function init()
    {
    }

    /**
     * @return mixed
     */
    protected function getInitialValue()
    {
        return null;
    }

    /**
     * @return mixed
     */
    protected function getResult()
    {
        return null;
    }

    /**
     * @param mixed $value
     *
     * @return mixed
     */
    abstract protected function iterate($value);

    /**
     * @param mixed $value
     */
    abstract protected function shouldContinue($value): bool;

    abstract protected function onTimeOut();
}
