<?php

/*
 * This file is part of the Slack Secret Santa project.
 *
 * (c) JoliCode <coucou@jolicode.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace JoliCode\SecretSanta\Controller;

use GuzzleHttp\Client;
use GuzzleHttp\Exception\ClientException;
use Joli\SlackSecretSanta\Application\TeamsApplication;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\RouterInterface;
use TheNetworg\OAuth2\Client\Provider\Azure;

class TeamsController extends AbstractController
{
    private $router;
    private $teamsClientId;
    private $teamsClientSecret;
    private $httpClient;

    public function __construct(RouterInterface $router, Client $httpClient, $teamsClientId, $teamsClientSecret)
    {
        $this->router = $router;
        $this->teamsClientId = $teamsClientId;
        $this->teamsClientSecret = $teamsClientSecret;
        $this->httpClient = $httpClient;
    }

    public function authenticate(Request $request): Response
    {
        return new Response("Todo: Redirect to Team 'Add the App' screen");
    }

    public function incomingBot(Request $request): Response
    {
        // bot configuré sur https://portal.azure.com/ avec le compte de Damien (CB fournie ^^)
        // puis BOT ajouté dans le manifest.

        // Get the token to speak with the BOT API. Valid 1h. @todo put me in cache.
        $response = $this->httpClient->post('https://login.microsoftonline.com/botframework.com/oauth2/v2.0/token', [
            'form_params' => [
                'grant_type' => 'client_credentials',
                'client_id' => $this->teamsClientId,
                'client_secret' => $this->teamsClientSecret,
                'scope' => 'https://api.botframework.com/.default',
            ]
        ]);

        $accessTokenData = json_decode($response->getBody()->getContents(), true);
        $accessToken = $accessTokenData['access_token'];

        $messageToBot = json_decode($request->getContent(false), true);

        if ($messageToBot['type'] !== 'message') {
            return new Response("Not a message, nothing we can do.");
        }

        $conversationId = $messageToBot['conversation']['id'];
        $activityId = $messageToBot['id'];

        $url = sprintf('%sv3/conversations/%s/activities/%s',
            $messageToBot['serviceUrl'], $conversationId, $activityId);

        // Dump response to any event!
        $responseData = [
            "type" => "message",
            "from" => $messageToBot['recipient'],
            "conversation" => [
                "id" => $conversationId,
                "name" => "conversation's name"
            ],
            "recipient" => $messageToBot['from'],
            "attachmentLayout" => "list",
            "attachments" => [
                [
                    "contentType" => "application/vnd.microsoft.card.thumbnail",
                    "content" => [
                        "buttons" => [
                            [
                              "type" => "openUrl",
                              "title" => "CLICK HERE FOR AWESOME WEBSITE",
                              "value" => "https://jolicode.com/"
                            ]
                        ]
                    ]
                ]
            ],
            "replyToId" => $activityId
        ];

        $this->httpClient->post($url, [
            'json' => $responseData,
            'headers' => [
                'Authorization' => sprintf('Bearer %s', $accessToken)
            ]
        ]);

        // get the team listing
        if (!isset($messageToBot['channelData']['team'])) {
            return new Response('Pas de team ID, pas de liste!');
        }

        $tenantId = $messageToBot['channelData']['tenant']['id'];
        $teamId = $messageToBot['channelData']['team']['id'];
        $urlTeamRoaster = sprintf('%sv3/conversations/%s/members', $messageToBot['serviceUrl'], $teamId);

        $response = $this->httpClient->get($urlTeamRoaster, [
            'headers' => [
                'Authorization' => sprintf('Bearer %s', $accessToken)
            ]
        ]);

        $teamRoasterData = json_decode($response->getBody()->getContents(), true);

        $loick = $teamRoasterData[2]; // @todo use a foreach to contact everyone

        // Start convo with Loick
        $createConvoData = [
            "bot"=> $messageToBot['recipient'],
            "members"=> [
                [ "id"=> $loick['id'], "name" => $loick['name'] ]
            ],
            "channelData"=> [
                "tenant"=> [
                    "id"=> $tenantId
                ]
            ]
        ];

        try {
            $createConvaResponse = $this->httpClient->post(sprintf('%sv3/conversations', $messageToBot['serviceUrl']), [
                'json' => $createConvoData,
                'headers' => [
                    'Authorization' => sprintf('Bearer %s', $accessToken)
                ]
            ]);
        } catch (ClientException $e) {
            dump($e->getRequest());
            dump($e->getResponse());
        }

        $convoData = json_decode($createConvaResponse->getBody()->getContents(), true);

        // POST /v3/conversations/{conversationId}/activities
        // Send the message
        $santaMessage = [
            "type" => "message",
            "from" => $messageToBot['recipient'],
            "conversation" => [
                "id" => $convoData['id'],
                "name" => "conversation's name"
            ],
            "recipient" => [
                "id" => $loick['id'],
                "name" => $loick['name']
            ],
            "text" => "Félicitation, tu es l'élu.",
        ];

        $sendMessageResponse = $this->httpClient->post(sprintf('%sv3/conversations/%s/activities', $messageToBot['serviceUrl'], $convoData['id']), [
            'json' => $santaMessage,
            'headers' => [
                'Authorization' => sprintf('Bearer %s', $accessToken)
            ]
        ]);

        return new Response('YEAH');
    }
}
