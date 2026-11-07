<?php

/*
 * This file is part of the Secret Santa project.
 *
 * (c) JoliCode <coucou@jolicode.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace JoliCode\SecretSanta\Tests\E2E;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use Playwright\Symfony\Test\PlaywrightTestCase;
use Predis\Client;

/**
 * Visual regression of the public pages. The pages of the Secret Santa journey are covered
 * by the checkpoints of FullRunTestCase. The stats page is left out: its counters move.
 */
#[Group('screenshot')]
class ScreenshotTest extends PlaywrightTestCase
{
    use ScreenshotAssertionTrait;

    #[DataProvider('providePages')]
    public function testPage(string $name, string $path): void
    {
        static::bootKernel();
        $redis = static::getContainer()->get(Client::class);

        // The homepage shows the number of draws: pin it, the counter moves with every run
        $total = $redis->get('stats:total');
        $redis->set('stats:total', 12345);

        try {
            $this->visit($path);

            $this->assertScreenshot($name);
        } finally {
            null === $total ? $redis->del('stats:total') : $redis->set('stats:total', $total);
        }
    }

    /**
     * @return iterable<string, array{string, string}>
     */
    public static function providePages(): iterable
    {
        yield 'homepage' => ['homepage', '/'];
        yield 'faq' => ['faq', '/faq'];
        yield 'hall-of-fame' => ['hall-of-fame', '/hall-of-fame'];
        yield 'spoil' => ['spoil', '/spoil'];
        yield 'terms-of-service' => ['terms-of-service', '/terms-of-service'];
        yield 'privacy-policy' => ['privacy-policy', '/privacy-policy'];
    }
}
