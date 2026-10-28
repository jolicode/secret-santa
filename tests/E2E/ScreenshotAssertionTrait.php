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

use Playwright\Page\PageInterface;

/**
 * Visual regression: compares a full-page screenshot with a reference image versioned in
 * tests/E2E/screenshots/. The references are rendered by the Chromium of the Docker image,
 * so they are only reproducible from there; `castor qa:update-screenshots` regenerates them.
 *
 * On failure, the actual, expected and diff images are written in test-failures/.
 */
trait ScreenshotAssertionTrait
{
    /** A pixel is considered changed when one of its RGB channels moves more than this, out of 255 */
    private const COLOR_TOLERANCE = 25;

    /** Changed pixels tolerated before failing: a few anti-aliasing differences are not a regression */
    private const MAX_DIFF_PIXELS = 50;

    protected function assertScreenshot(string $name): void
    {
        $page = $this->getPage();
        $page->waitForLoadState('networkidle');
        $page->evaluate('() => document.fonts.ready.then(() => true)');
        // The clicks of the journey leave the mouse over an element and the page scrolled to it,
        // which moves the :hover styles and the sticky blocks
        $page->mouse()->move(0, 0);
        // The CSS scroll-behavior is smooth: an instant scroll, or the screenshot is taken mid-scroll
        $page->evaluate("() => window.scrollTo({ top: 0, left: 0, behavior: 'instant' })");

        $reference = __DIR__ . "/screenshots/{$name}.png";
        $failuresDir = getcwd() . '/test-failures';
        $actual = "{$failuresDir}/{$name}-actual.png";
        if (!is_dir($failuresDir)) {
            mkdir($failuresDir, 0o777, true);
        }
        $page->screenshot($actual, ['fullPage' => true, 'animations' => 'disabled', 'caret' => 'hide', 'scale' => 'css']);

        if (getenv('UPDATE_SCREENSHOTS')) {
            rename($actual, $reference);
            $this->addToAssertionCount(1);

            return;
        }

        self::assertFileExists($reference, "No reference screenshot for \"{$name}\", run `castor qa:update-screenshots` to create it.");

        if (hash_file('sha256', $reference) === hash_file('sha256', $actual)) {
            unlink($actual);

            return;
        }

        $expectedCopy = "{$failuresDir}/{$name}-expected.png";
        $diff = "{$failuresDir}/{$name}-diff.png";
        copy($reference, $expectedCopy);
        $expected = imagecreatefrompng($reference);
        $got = imagecreatefrompng($actual);
        self::assertNotFalse($expected);
        self::assertNotFalse($got);
        $count = $this->writeDiff($expected, $got, $diff);

        if ($count <= self::MAX_DIFF_PIXELS) {
            // Not a regression: leave nothing behind, or the report of another failed test would include it
            unlink($actual);
            unlink($expectedCopy);
            unlink($diff);
            $this->addToAssertionCount(1);

            return;
        }

        self::fail("Screenshot \"{$name}\" differs from the reference by {$count} pixels, see {$diff}.");
    }

    abstract protected function getPage(): PageInterface;

    /**
     * Writes the reference faded out, with the changed pixels in red. The canvas covers both
     * images: a pixel that exists in only one of them (the page grew or shrank) counts as changed.
     *
     * @return int The number of changed pixels
     */
    private function writeDiff(\GdImage $expected, \GdImage $got, string $path): int
    {
        [$expectedWidth, $expectedHeight] = [imagesx($expected), imagesy($expected)];
        [$gotWidth, $gotHeight] = [imagesx($got), imagesy($got)];
        $width = max($expectedWidth, $gotWidth);
        $height = max($expectedHeight, $gotHeight);
        $diff = imagecreatetruecolor($width, $height);
        self::assertNotFalse($diff);
        imagefill($diff, 0, 0, (int) imagecolorallocate($diff, 255, 255, 255));
        imagecopymerge($diff, $expected, 0, 0, 0, 0, $expectedWidth, $expectedHeight, 30);
        $red = (int) imagecolorallocate($diff, 255, 0, 0);

        $count = 0;
        for ($y = 0; $y < $height; ++$y) {
            for ($x = 0; $x < $width; ++$x) {
                if ($x >= $expectedWidth || $y >= $expectedHeight || $x >= $gotWidth || $y >= $gotHeight) {
                    ++$count;
                    imagesetpixel($diff, $x, $y, $red);

                    continue;
                }
                $a = imagecolorat($expected, $x, $y);
                $b = imagecolorat($got, $x, $y);
                if ($a === $b) {
                    continue;
                }
                $delta = max(
                    abs((($a >> 16) & 0xFF) - (($b >> 16) & 0xFF)),
                    abs((($a >> 8) & 0xFF) - (($b >> 8) & 0xFF)),
                    abs(($a & 0xFF) - ($b & 0xFF)),
                );
                if ($delta > self::COLOR_TOLERANCE) {
                    ++$count;
                    imagesetpixel($diff, $x, $y, $red);
                }
            }
        }
        imagepng($diff, $path);

        return $count;
    }
}
