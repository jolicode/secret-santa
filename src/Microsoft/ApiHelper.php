<?php

/*
 * This file is part of the Secret Santa project.
 *
 * (c) JoliCode <coucou@jolicode.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace JoliCode\SecretSanta\Microsoft;

use GuzzleHttp\Client;
use GuzzleHttp\Exception\ClientException;
use JoliCode\SecretSanta\Exception\AuthenticationException;

class ApiHelper
{
    private $httpClient;
    private $teamsClientId;
    private $teamsClientSecret;

    public function __construct(Client $httpClient, string $teamsClientId, string $teamsClientSecret)
    {
        $this->httpClient = $httpClient;
        $this->teamsClientId = $teamsClientId;
        $this->teamsClientSecret = $teamsClientSecret;
    }

    public function getAccessToken(): string
    {
        $response = $this->httpClient->post('https://login.microsoftonline.com/botframework.com/oauth2/v2.0/token', [
            'form_params' => [
                'grant_type' => 'client_credentials',
                'client_id' => $this->teamsClientId,
                'client_secret' => $this->teamsClientSecret,
                'scope' => 'https://api.botframework.com/.default',
            ],
        ]);

        $accessTokenData = json_decode($response->getBody()->getContents(), true);

        if (!empty($accessTokenData['access_token'])) {
            throw new AuthenticationException('Could not retrieve access token');
        }

        return $accessTokenData['access_token'];
    }

    public function getMembers(Configuration $configuration): array
    {
        return $members = $this->callApi($configuration, sprintf('conversations/%s/members', $configuration->getTeamId()));
    }

    public function sendMessage(Configuration $configuration, string $userId, string $message): void
    {
        $conversation = $this->createConversation($configuration, $userId);

        $data = [
            'type' => 'message',
            'from' => $configuration->getBot(),
            'conversation' => [
                'id' => $conversation['id'],
                //'name' => 'conversation\'s name',
            ],
            'recipient' => [
                'id' => $userId,
                //'name' => $userName,
            ],
            'text' => $message,
        ];

        $this->callApi($configuration, sprintf('conversations/%s/activities', $conversation['id']), $data);
    }

    public function callApi(Configuration $configuration, string $endpoint, array $data = []): array
    {
        try {
            $response = $this->httpClient->post(sprintf('%sv3/%s', $configuration->getServiceUrl(), $endpoint), [
                'json' => $data,
                'headers' => [
                    'Authorization' => sprintf('Bearer %s', $configuration->getToken()),
                ],
            ]);

            return json_decode($response->getBody()->getContents(), true);
        } catch (ClientException $e) {
            throw new \RuntimeException('Error when calling the API', 0, $e);
        }
    }

    private function createConversation(Configuration $configuration, string $userId): array
    {
        $data = [
            'bot' => $configuration->getBot(),
            'members' => [
                [
                    'id' => $userId,
                    //'name' => $userName,
                ],
            ],
            'channelData' => [
                'tenant' => [
                    'id' => $configuration->getTenantId(),
                ],
            ],
        ];

        return $this->callApi($configuration, 'conversations', $data);
    }
}
