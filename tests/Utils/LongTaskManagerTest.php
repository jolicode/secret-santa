<?php

/*
 * This file is part of the Secret Santa project.
 *
 * (c) JoliCode <coucou@jolicode.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace JoliCode\SecretSanta\Tests\Utils;

use JoliCode\SecretSanta\Utils\LongTaskManager;
use PHPUnit\Framework\TestCase;

class LongTaskManagerTest extends TestCase
{
    const TIMEOUT = 2;

    /** @var LongTaskManager */
    private $SUT;

    protected function setUp()
    {
        $this->SUT = new LongTaskManager();
    }

    public function test_it_does_not_timeout_and_iterates_correctly()
    {
        $iterationCount = 0;

        $this->SUT->process(function (int $i) use (&$iterationCount) {
            ++$iterationCount;

            return $i + 1;
        }, function (int $i) {
            return $i < 2;
        }, function () {
            throw new \Exception('Timeout!');
        }, 0, self::TIMEOUT);

        self::assertSame(2, $iterationCount);
    }

    public function test_it_timeouts()
    {
        $iterationCount = 0;

        try {
            $this->SUT->process(function (int $i) use (&$iterationCount) {
                sleep(3);

                ++$iterationCount;

                return $i + 1;
            }, function (int $i) {
                return $i < 2;
            }, function () {
                throw new \Exception('Timeout!');
            }, 0, self::TIMEOUT);

            self::fail('No timeout were triggered');
        } catch (\Exception $e) {
            self::assertSame('Timeout!', $e->getMessage());
        }

        self::assertSame(1, $iterationCount);
    }
}
