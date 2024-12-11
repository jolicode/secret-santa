<?php

/*
 * This file is part of the Secret Santa project.
 *
 * (c) JoliCode <coucou@jolicode.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace JoliCode\SecretSanta\Discord;

use JoliCode\SecretSanta\Model\File;
use Symfony\Component\Mime\Part\DataPart;
use Symfony\Component\Mime\Part\Multipart\FormDataPart;
use Symfony\Contracts\HttpClient\HttpClientInterface;
use Symfony\Contracts\HttpClient\ResponseInterface;

class ApiHelper
{
    public function __construct(
        private string $discordBotToken,
        private HttpClientInterface $httpClient,
    ) {
    }

    /**
     * @return list<mixed>
     */
    public function getMembersInGuild(int $guildId, ?int $after = null): array
    {
        $url = '/guilds/' . $guildId . '/members?limit=200';
        if ($after) {
            $url .= '&after=' . $after;
        }

        $response = $this->callApi('GET', $url, []);

        if (200 !== $response->getStatusCode()) {
            throw new \RuntimeException('Failed to retrieve members: ' . $response->getContent(false));
        }

        return $response->toArray();
    }

    /**
     * @return list<mixed>
     */
    public function getRolesInGuild(int $guildId): array
    {
        $response = $this->callApi('GET', '/guilds/' . $guildId . '/roles', []);

        if (200 !== $response->getStatusCode()) {
            throw new \RuntimeException('Failed to retrieve roles: ' . $response->getContent(false));
        }

        return $response->toArray();
    }

    public function sendMessage(int $userId, string $message, ?File $file = null): void
    {
        // Create a private channel with the user
        $response = $this->callApi('POST', '/users/@me/channels', [
            'json' => [
                'recipient_id' => $userId,
            ],
        ]);

        if (200 !== $response->getStatusCode()) {
            throw new \RuntimeException('Failed to create private channel: ' . $response->getContent(false));
        }

        $channel = $response->toArray();
        $channelId = $channel['id'];

        $body = [];
        $payload = [
            'content' => $message,
        ];

        if ($file) {
            $payload['attachements'][] = [
                'id' => 0,
                'description' => 'Secret Santa spoiling code',
                'filename' => $file->name,
            ];
            $body['files[0]'] = new DataPart($file->content, $file->name, 'text/plain');
        }

        $body['payload_json'] = new DataPart(json_encode($payload), null, 'application/json');

        $formData = new FormDataPart($body);

        $options = [
            'body' => $formData->bodyToString(),
            'headers' => $formData->getPreparedHeaders()->toArray(),
        ];

        // Send message to the private channel
        $response = $this->callApi('POST', "/channels/{$channelId}/messages", $options);

        if (200 !== $response->getStatusCode()) {
            throw new \RuntimeException('Failed to send private message: ' . $response->getContent(false));
        }
    }

    /**
     * @param array<string, mixed> $options
     */
    private function callApi(string $method, string $endpoint, array $options): ResponseInterface
    {
        $options['headers'] ??= [];
        $options['headers'][] = 'Authorization: Bot ' . $this->discordBotToken;

        return $this->httpClient->request($method, 'https://discord.com/api/v10/' . $endpoint, $options);
    }
}
