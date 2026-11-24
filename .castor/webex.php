<?php

/*
 * This file is part of the Secret Santa project.
 *
 * (c) JoliCode <coucou@jolicode.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace webex;

use Castor\Attribute\AsOption;
use Castor\Attribute\AsTask;

use function Castor\http_client;
use function Castor\io;

const WEBEX_API = 'https://webexapis.com/v1';

/**
 * Webex only sends the X-Spark-Signature header when the webhook has a secret. The bot
 * webhook targeting the given URL is updated with a new secret, or created if missing.
 *
 * @see https://developer.webex.com/docs/api/v1/webhooks/update-a-webhook
 * @see https://developer.webex.com/blog/using-a-webhook-secret
 */
#[AsTask(name: 'setup-webhook', description: 'Sets a signing secret on the Webex bot webhook (creates the webhook if missing)')]
function setup_webhook(
    #[AsOption(description: 'URL Webex calls for each message sent to the bot')]
    string $targetUrl = 'https://secret-santa.team/bot/webex',
    #[AsOption(description: 'Name of the webhook, when it has to be created')]
    string $name = 'production-webhook',
    #[AsOption(description: 'Secret used to sign the calls (a random one is generated if empty)')]
    string $secret = '',
    #[AsOption(description: 'Only show what would be done')]
    bool $dryRun = false,
): int {
    io()->title('Setting up the Webex bot webhook');

    // The webhook must belong to the bot to be notified of the messages sent to it
    $botToken = getenv('WEBEX_BOT_TOKEN') ?: io()->askHidden('Webex bot token (WEBEX_BOT_TOKEN)');
    if (!$botToken) {
        io()->error('A Webex bot token is required.');

        return 1;
    }

    $client = http_client()->withOptions([
        'auth_bearer' => $botToken,
        'headers' => ['accept' => 'application/json'],
    ]);

    $webhooks = $client->request('GET', WEBEX_API . '/webhooks', ['query' => ['max' => 100]])->toArray()['items'] ?? [];

    io()->section('Existing webhooks of the bot');
    if ($webhooks) {
        io()->table(
            ['Id', 'Name', 'Target URL', 'Resource', 'Event', 'Status', 'Has a secret'],
            array_map(static fn (array $webhook) => [
                $webhook['id'],
                $webhook['name'] ?? '',
                $webhook['targetUrl'] ?? '',
                $webhook['resource'] ?? '',
                $webhook['event'] ?? '',
                $webhook['status'] ?? '',
                ($webhook['secret'] ?? '') ? 'yes' : 'no',
            ], $webhooks),
        );
    } else {
        io()->text('None.');
    }

    $matchingWebhooks = array_values(array_filter($webhooks, static fn (array $webhook) => ($webhook['targetUrl'] ?? null) === $targetUrl));

    if (\count($matchingWebhooks) > 1) {
        io()->error(\sprintf('%d webhooks target %s: the bot would answer several times to each message. Delete the extra ones first.', \count($matchingWebhooks), $targetUrl));

        return 1;
    }

    $webhook = $matchingWebhooks[0] ?? null;
    $secret = $secret ?: bin2hex(random_bytes(32));

    io()->section('Plan');
    io()->text($webhook
        ? \sprintf('Update the webhook "%s" (%s) with a new secret.', $webhook['name'] ?? '', $webhook['id'])
        : \sprintf('Create the webhook "%s" on messages/created, targeting %s, with a secret.', $name, $targetUrl));
    io()->warning([
        'The application rejects every unsigned call to /bot/webex.',
        'WEBEX_WEBHOOK_SECRET must hold the new secret where the application runs, or the bot will stop answering.',
    ]);

    if ($dryRun) {
        io()->note('Dry run: nothing was changed.');

        return 0;
    }

    if (!io()->confirm('Apply this plan?', false)) {
        return 1;
    }

    if ($webhook) {
        // "name" and "targetUrl" are required, even when unchanged
        $client->request('PUT', WEBEX_API . '/webhooks/' . $webhook['id'], [
            'json' => [
                'name' => $webhook['name'],
                'targetUrl' => $webhook['targetUrl'],
                'secret' => $secret,
            ],
        ])->toArray();

        io()->success(\sprintf('Webhook %s updated.', $webhook['id']));
    } else {
        $created = $client->request('POST', WEBEX_API . '/webhooks', [
            'json' => [
                'name' => $name,
                'targetUrl' => $targetUrl,
                'resource' => 'messages',
                'event' => 'created',
                'secret' => $secret,
            ],
        ])->toArray();

        io()->success(\sprintf('Webhook %s created.', $created['id']));
    }

    io()->text('Set this value in WEBEX_WEBHOOK_SECRET where the application runs (Clever Cloud for production):');
    io()->newLine();
    io()->writeln($secret);
    io()->newLine();

    return 0;
}
