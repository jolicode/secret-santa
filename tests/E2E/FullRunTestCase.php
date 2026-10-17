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

use JoliCode\SecretSanta\Tests\Controller\SessionPrepareTrait;
use JoliCode\SecretSanta\Tests\Fake\FakeChatApi;
use Playwright\Dialog\DialogInterface;
use Playwright\Page\PageInterface;
use Playwright\Symfony\Test\PlaywrightTestCase;
use Symfony\Component\HttpFoundation\Request;

/**
 * Drives the whole journey of a chat application in a real browser, from "run" to "finish", against a fake API.
 *
 * The browser requests are handled in-process by the kernel, so the session and the fake API
 * instance are shared with the test. Only the OAuth dance is skipped: the token and admin are
 * put in session directly. The fake exposes five users named after NAMES, the second and third
 * being in a "Devs" group, the first one being the admin.
 */
abstract class FullRunTestCase extends PlaywrightTestCase
{
    use SessionPrepareTrait;

    /** @var array<string|int, string> User id => name shown on the spoil page, admin first */
    protected const NAMES = [];

    public function testFullRun(): void
    {
        $api = $this->logInAsAdmin();

        $page = $this->runUntilFinish($api);
        self::assertPageContains('Well done! All messages were sent');

        // Session is cleaned once everything is sent, so the token is gone too
        $this->visit('/participants/' . static::application());
        self::assertSame('/auth/' . static::application(), $this->getLastResponse()?->headers->get('Location'));

        // Chat side: 1 sample for the admin, 1 admin message, then 1 secret message per participant
        [$admin, $bob] = [$this->id(0), $this->id(1)];
        $messages = $api->postedMessages();
        self::assertCount(6, $messages);

        $sampleMessage = array_shift($messages);
        self::assertSame($admin, $sampleMessage['to']);
        self::assertStringContainsString('sample', $sampleMessage['text']);

        $adminMessage = array_shift($messages);
        self::assertSame($admin, $adminMessage['to']);
        self::assertStringContainsString('retrieve the secret repartition', $adminMessage['text']);
        self::assertStringContainsString('https://localhost/spoil', $adminMessage['text']);

        $associations = [];
        foreach ($messages as $message) {
            $associations[$message['to']] = $this->receiverOf($message['text']);

            self::assertStringContainsString('Budget: 20 euros', $message['text']);
            self::assertStringContainsString('by admin', $message['text']);
        }

        $participants = \array_slice($this->ids(), 0, 4);
        self::assertEqualsCanonicalizing($participants, array_keys($associations), 'Every participant is a giver');
        self::assertEqualsCanonicalizing($participants, array_values($associations), 'Every participant is a receiver');
        foreach ($associations as $giver => $receiver) {
            self::assertNotEquals($giver, $receiver);
        }
        self::assertNotSame($bob, $associations[$admin], 'Exclusion is respected');

        foreach ($messages as $message) {
            if ($bob === $associations[$message['to']]) {
                self::assertStringContainsString('Loves chocolate', $message['text'], 'Note about Bob goes to his giver');
            } else {
                self::assertStringNotContainsString('Loves chocolate', $message['text']);
            }
        }

        // The spoil code sent to the admin decodes to the same repartition
        $page = $this->visit('/spoil');
        $page->locator('[name="code"]')->fill($this->spoilCode($adminMessage));
        $page->getByRole('button', ['name' => 'Decode!'])->click();
        $page->getByText('Here is the secret repartition')->waitFor();
        $text = $page->locator('body')->innerText();
        foreach ($associations as $giver => $receiver) {
            self::assertStringContainsString(\sprintf('%s must offer a gift to %s', static::NAMES[$giver], static::NAMES[$receiver]), $text);
        }
    }

    public function testFailedMessagesAreReportedAndCanBeRetried(): void
    {
        $api = $this->logInAsAdmin();
        $carol = $this->id(2);
        $api->failingUsers = [$carol];

        $page = $this->runUntilFinish($api);
        self::assertPageContains('All the messages are not sent yet');
        self::assertStringContainsString(static::NAMES[$carol] . ' must offer a gift to xxxxx', $page->locator('body')->innerText());

        $api->failingUsers = [];
        $page->locator('#retry-button')->click();
        $this->followRedirect();

        $page->waitForURL('**/finish/*');
        self::assertPageContains('Well done! All messages were sent');

        // Sample, admin message, 4 attempts in shuffle order, then the retry for Carol only
        $recipients = array_column($api->postedMessages(), 'to');
        self::assertCount(7, $recipients);
        self::assertSame([$this->id(0), $this->id(0)], \array_slice($recipients, 0, 2));
        self::assertEqualsCanonicalizing(\array_slice($this->ids(), 0, 4), \array_slice($recipients, 2, 4));
        self::assertSame($carol, $recipients[6]);
    }

    /**
     * Playwright does not expose the Sec-Fetch-* headers of intercepted requests, and the app's
     * "no-referrer" policy makes the browser send "Origin: null" on form posts. Restore what a real
     * browser sends so the stateless CSRF check of Symfony passes like in production.
     */
    public function beforeRequest(Request $request): void
    {
        $request->headers->set('Sec-Fetch-Site', 'same-origin');
    }

    abstract protected static function application(): string;

    /**
     * @return array<string, mixed> The session attributes making the first user of NAMES the authenticated admin
     */
    abstract protected function sessionAttributes(): array;

    abstract protected function fakeApi(): FakeChatApi;

    /**
     * @return string The id of the user a secret message tells to gift
     */
    abstract protected function receiverOf(string $message): string;

    /**
     * @param array{to: string, text: string, ...} $adminMessage
     */
    protected function spoilCode(array $adminMessage): string
    {
        preg_match('/```(.+?)```/s', $adminMessage['text'], $matches);

        return $matches[1];
    }

    /**
     * @return list<string> Numeric ids are strings for the application, but PHP makes them int array keys
     */
    private function ids(): array
    {
        return array_map('strval', array_keys(static::NAMES));
    }

    private function id(int $position): string
    {
        return $this->ids()[$position];
    }

    private function logInAsAdmin(): FakeChatApi
    {
        static::bootKernel();

        $this->setCookie('MOCKSESSID', $this->storeSession($this->sessionAttributes()));

        // Stop on redirects like BrowserKit does, and follow them by hand with followRedirect(): when
        // the client follows them itself, every click that triggers a redirect waits for the full
        // navigation timeout before the redirect target is loaded.
        static::getPlaywrightClient()->followRedirects(false);

        return $this->fakeApi();
    }

    private function followRedirect(): void
    {
        static::getPlaywrightClient()->followRedirect();
    }

    /**
     * Intercepted requests are only processed while the test talks to Playwright, so poll with a cheap call.
     */
    private function waitUntil(PageInterface $page, callable $condition): void
    {
        $deadline = microtime(true) + 5;
        while (!$condition()) {
            self::assertLessThan($deadline, microtime(true), 'Timed out waiting for the condition');
            $page->evaluate('() => null');
            usleep(100_000);
        }
    }

    /**
     * @return PageInterface The page, on /finish/{hash}
     */
    private function runUntilFinish(FakeChatApi $api): PageInterface
    {
        [$alice, $bob, $carol, $dave] = \array_slice($this->ids(), 0, 4);
        $application = static::application();

        $page = $this->visit('/run/' . $application);
        $page->setDefaultTimeout(15000);
        $this->followRedirect();

        // Users are loaded page by page in the background, then the browser is redirected
        $page->waitForURL('**/participants/' . $application);
        self::assertPageContains('Available users (5)');

        // Nobody selected yet
        self::assertSame('0 person', $page->locator('#summary-count')->textContent());

        // The group button checks its members
        $page->locator('#group-list')->getByText('Devs', ['exact' => true])->click();
        self::assertTrue($page->locator('#id_' . $bob)->isChecked());
        self::assertTrue($page->locator('#id_' . $carol)->isChecked());
        self::assertSame('2 people', $page->locator('#summary-count')->textContent());

        // Search hides the non-matching users, "check all" only checks the visible ones.
        // The search listens to keyup, so the text has to be typed key by key.
        $page->locator('#user-search-input')->pressSequentially('ali');
        $page->locator('#all-checker')->click();
        self::assertTrue($page->locator('#id_' . $alice)->isChecked());
        self::assertFalse($page->locator('#id_' . $dave)->isChecked());
        // The clear icon has no size without the icon font, so the query is cleared from the keyboard
        $page->locator('#user-search-input')->clear();
        $page->locator('#user-search-input')->press('Backspace');
        self::assertTrue($page->locator(\sprintf('label[for="id_%s"]', $dave))->isVisible());

        // The checkbox itself is styled away, the label is what gets clicked
        $page->locator(\sprintf('label[for="id_%s"]', $dave))->click();
        self::assertSame('4 people', $page->locator('#summary-count')->textContent());

        $page->locator('#submit-button')->click();
        $this->followRedirect();
        $page->waitForURL('**/exclusions/' . $application);

        // Alice must not gift Bob: pick him in the dialog
        $page->locator(\sprintf('.exclusion-container[data-user-id="%s"] + .js-add-exclusion', $alice))->click();
        $dialog = $page->locator('.user-dialog');
        $dialog->waitFor();
        $dialog->locator('#exclusion-search-input')->fill('bob');
        $dialog->locator(\sprintf('.js-select-user[data-user-id="%s"]', $bob))->click();
        $dialog->locator('.js-confirm-exclusions')->click();
        self::assertSame(
            $bob,
            $page->locator(\sprintf('.exclusion-container[data-user-id="%s"] input[type="hidden"]', $alice))->inputValue(),
        );

        $page->locator('#submit-button')->click();
        $this->followRedirect();
        $page->waitForURL('**/message/' . $application);

        $page->locator('#message_message')->fill('Budget: 20 euros');
        // The length counter listens to keyup, so the note has to be typed key by key
        $page->locator('#message_notes_' . $bob)->pressSequentially('Loves chocolate');
        self::assertSame('15', $page->locator(\sprintf('[data-length-field="%s"]', $bob))->textContent());

        // The sample button posts the form in the background and confirms through an alert
        $alerts = [];
        $page->events()->onDialog(static function (DialogInterface $dialog) use (&$alerts): void {
            $alerts[] = $dialog->message();
            $dialog->accept();
        });
        $page->locator('#sample-message-button')->click();
        $this->waitUntil($page, static function () use (&$alerts): bool { return [] !== $alerts; });
        self::assertSame(['We have sent you a fake message. Check it now before finishing the Secret Santa.'], $alerts);
        self::assertCount(1, $api->postedMessages());
        self::assertSame($alice, $api->postedMessages()[0]['to']);
        self::assertStringContainsString('Loves chocolate', $api->postedMessages()[0]['text'], 'Sample uses the notes');

        $page->locator('#submit-button')->click();
        $this->followRedirect();
        $page->waitForURL('**/validate/' . $application);

        // The sending page posts in the background until everything is sent, then lands on /finish
        $page->locator('#submit-button')->click();
        $this->followRedirect();
        $page->waitForURL('**/finish/*');

        return $page;
    }
}
