<?php

/*
 * This file is part of the Secret Santa project.
 *
 * (c) JoliCode <coucou@jolicode.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace JoliCode\SecretSanta\Tests\Controller;

use JoliCode\SecretSanta\Tests\Fake\FakeWebexApi;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;

class WebexControllerTest extends BaseWebTestCase
{
    // Same value as WEBEX_WEBHOOK_SECRET in .env.test
    private const WEBHOOK_SECRET = 'webhook-secret-for-tests';

    public function testBotAnswersSignedMessage(): void
    {
        $client = static::createClient();
        $payload = $this->messagePayload();

        $this->postWebhook($client, $payload, hash_hmac('sha1', $payload, self::WEBHOOK_SECRET));

        self::assertResponseIsSuccessful();
        $messages = static::getContainer()->get(FakeWebexApi::class)->postedMessages();
        self::assertCount(1, $messages);
        self::assertSame('W2', $messages[0]['to']);
    }

    public function testBotRejectsUnsignedMessage(): void
    {
        $client = static::createClient();

        $this->postWebhook($client, $this->messagePayload(), null);

        self::assertResponseStatusCodeSame(403);
        self::assertSame([], static::getContainer()->get(FakeWebexApi::class)->postedMessages());
    }

    public function testBotRejectsMessageSignedWithAnotherSecret(): void
    {
        $client = static::createClient();
        $payload = $this->messagePayload();

        $this->postWebhook($client, $payload, hash_hmac('sha1', $payload, 'not-the-secret'));

        self::assertResponseStatusCodeSame(403);
        self::assertSame([], static::getContainer()->get(FakeWebexApi::class)->postedMessages());
    }

    public function testBotRejectsTamperedMessage(): void
    {
        $client = static::createClient();
        $signature = hash_hmac('sha1', $this->messagePayload('W2'), self::WEBHOOK_SECRET);

        $this->postWebhook($client, $this->messagePayload('W3'), $signature);

        self::assertResponseStatusCodeSame(403);
        self::assertSame([], static::getContainer()->get(FakeWebexApi::class)->postedMessages());
    }

    public function testBotRejectsSignedMalformedPayload(): void
    {
        $client = static::createClient();
        $payload = '{"resource": "messages", "event": "created", "data": {}}';

        $this->postWebhook($client, $payload, hash_hmac('sha1', $payload, self::WEBHOOK_SECRET));

        self::assertResponseStatusCodeSame(400);
        self::assertSame([], static::getContainer()->get(FakeWebexApi::class)->postedMessages());
    }

    private function messagePayload(string $personId = 'W2'): string
    {
        return (string) json_encode([
            'resource' => 'messages',
            'event' => 'created',
            'data' => [
                'personId' => $personId,
                'personEmail' => 'bob@example.com',
            ],
        ]);
    }

    private function postWebhook(KernelBrowser $client, string $payload, ?string $signature): void
    {
        $server = ['CONTENT_TYPE' => 'application/json'];
        if (null !== $signature) {
            $server['HTTP_X_SPARK_SIGNATURE'] = $signature;
        }

        $client->request('POST', '/bot/webex', server: $server, content: $payload);
    }
}
