<?php

/*
 * This file is part of the Secret Santa project.
 *
 * (c) JoliCode <coucou@jolicode.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace JoliCode\SecretSanta\Controller;

use JoliCode\SecretSanta\Application\TeamsApplication;
use JoliCode\SecretSanta\Microsoft\ApiHelper;
use JoliCode\SecretSanta\Microsoft\Configuration;
use JoliCode\SecretSanta\User;
use Predis\ClientInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

class TeamsController extends AbstractController
{
    private $redisClient;
    private $apiHelper;
    private $teamsApplication;

    public function __construct(ClientInterface $redisClient, ApiHelper $apiHelper, TeamsApplication $teamsApplication)
    {
        $this->redisClient = $redisClient;
        $this->apiHelper = $apiHelper;
        $this->teamsApplication = $teamsApplication;
    }

    public function start(Request $request): Response
    {
        // bot configuré sur https://portal.azure.com/ avec le compte de Damien (CB fournie ^^)
        // puis BOT ajouté dans le manifest.

        return $this->render('microsoft/start.html.twig', [
            'teams_url' => 'https://link-to-add-the-bot-to-the-team', // todo url
        ]);
    }

    public function incomingBot(Request $request): Response
    {
        $messageToBot = json_decode($request->getContent(false), true);

        // Not a message, nothing we can do.
        if ('message' !== $messageToBot['type']) {
            return new Response();
        }

        $admin = new User('todo id', 'todo name');
        $accessToken = $this->apiHelper->getAccessToken();
        $serviceUrl = $messageToBot['serviceUrl'];
        $teamId = $messageToBot['channelData']['team']['id'];
        $teamName = $messageToBot['channelData']['team']['name'];
        $tenantId = $messageToBot['channelData']['tenant']['id'];
        $recipient = $messageToBot['recipient'];

        $configuration = new Configuration(
            $admin,
            $accessToken,
            $serviceUrl,
            $teamId,
            $teamName,
            $tenantId,
            $recipient
        );

        $id = uniqid();
        $this->redisClient->set($this->getRedisConfigurationKey($id), serialize($configuration), 'ex', 60 * 60);

        $conversationId = $messageToBot['conversation']['id'];
        $activityId = $messageToBot['id'];

        // Answer link to any event!
        $responseData = [
            'type' => 'message',
            'from' => $recipient,
            'conversation' => [
                'id' => $conversationId,
                //'name' => "conversation's name",
            ],
            'recipient' => $messageToBot['from'],
            'attachmentLayout' => 'list',
            'attachments' => [
                [
                    'contentType' => 'application/vnd.microsoft.card.thumbnail',
                    'content' => [
                        'buttons' => [
                            [
                              'type' => 'openUrl',
                              'title' => 'Click here to start a Santa Santa with your team (link expires in 1 hour)',
                              'value' => $this->generateUrl('init', [
                                  'id' => $id,
                              ], UrlGeneratorInterface::ABSOLUTE_URL),
                            ],
                        ],
                    ],
                ],
            ],
            'replyToId' => $activityId,
        ];

        $this->apiHelper->callApi($configuration, sprintf('conversations/%s/activities/%s', $conversationId, $activityId, $responseData));

        return new Response();
    }

    public function init(string $id): Response
    {
        $configuration = $this->redisClient->get($this->getRedisConfigurationKey($id));

        if (!$configuration) {
            $this->createNotFoundException();
        }

        $configuration = unserialize($configuration);

        $this->teamsApplication->setConfiguration($configuration);

        return $this->redirectToRoute('run', [
            'application' => $this->teamsApplication->getCode(),
        ]);
    }

    private function getRedisConfigurationKey(string $id)
    {
        return sprintf('teams-%s', $id);
    }
}
