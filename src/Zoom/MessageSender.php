<?php

/*
 * This file is part of the Secret Santa project.
 *
 * (c) JoliCode <coucou@jolicode.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace JoliCode\SecretSanta\Zoom;

use JoliCode\SecretSanta\Exception\MessageSendFailedException;
use JoliCode\SecretSanta\Model\SecretSanta;
use Symfony\Contracts\HttpClient\Exception\ExceptionInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

class MessageSender
{
    const SANTA_EMOJI = "\xF0\x9F\x8E\x85";
    const GIFT_EMOJI = "\xF0\x9F\x8E\x81";

    private $httpClient;
    private $zoomBotJid;

    public function __construct(HttpClientInterface $httpClient, string $zoomBotJid)
    {
        $this->httpClient = $httpClient;
        $this->zoomBotJid = $zoomBotJid;
    }

    /**
     * @throws MessageSendFailedException
     */
    public function sendSecretMessage(SecretSanta $secretSanta, string $giver, string $receiver, string $token, string $accountId, bool $isSample): void
    {
        $body = [];
        $body['robot_jid'] = $this->zoomBotJid;
        $body['to_jid'] = self::transformUserIdToJID($giver);
        $body['account_id'] = $accountId;

        // Does not work on linux!
        $body['is_markdown_support'] = true;

        // Doc: https://marketplace.zoom.us/docs/guides/chatbots/customizing-messages/message-with-markdown
        $body['content'] = [
            'body' => [
                [
                    'type' => 'message',
                    'text' => 'Hi! You have been *selected* to be part of a Secret Santa ' . self::SANTA_EMOJI . '!',
                ],
            ],
        ];

        if ($isSample) {
            $body['content']['head'] = [
                'type' => 'message',
                'text' => '_Find below a *sample* of the message that will be sent to all participants of your Secret Santa._',
            ];
        }

        $receiverUser = $secretSanta->getUser($receiver);
        $body['content']['body'][] = [
            'type' => 'message',
            'text' => sprintf("Someone will get you a gift and *you have been chosen to gift:*\n\n" . self::GIFT_EMOJI . ' <!%s|%s> ' . self::GIFT_EMOJI . "\n\n",
                $receiver,
                $receiverUser->getName()
            ),
        ];

        if (!empty($userNote = $secretSanta->getUserNote($receiver))) {
            $body['content']['body'][] = [
                'type' => 'message',
                'text' => sprintf('*Here is some details about <!%s|%s>:*',
                    $receiver,
                    $receiverUser->getName()
                ),
            ];

            $body['content']['body'][] = [
                'type' => 'message',
                'text' => $userNote,
            ];
        }

        if (!empty($secretSanta->getAdminMessage())) {
            $body['content']['body'][] = [
                'type' => 'message',
                'text' => '*Here is a message from the Secret Santa admin:*',
            ];

            $body['content']['body'][] = [
                'type' => 'message',
                'text' => $secretSanta->getAdminMessage(),
            ];
        } else {
            $body['content']['body'][] = [
                'type' => 'message',
                'text' => 'If you have any question please ask your Secret Santa admin',
            ];
        }

        $footer = '_Organized with https://secret-santa.team/';

        if ($admin = $secretSanta->getAdmin()) {
            $footer .= sprintf(' by <!%s|%s>_', $admin->getIdentifier(), $admin->getName());
        } else {
            $footer .= '_';
        }

        $body['content']['body'][] = [
            'type' => 'message',
            'text' => $footer,
            'italic' => true, // Not working on android
        ];

        // Add color sidebar
        // Message appears empty on Android, no sidebar possible at the moment.
        /*$oldBody = $body['content']['body'];
        $body['content']['body'] = [
            'type' => 'section',
            'sidebar_color' => '#FFCC33',
            'sections' => $oldBody
        ];*/

        try {
            $this->httpClient->request('POST', 'https://api.zoom.us/v2/im/chat/messages', [
                'json' => $body,
                'auth_bearer' => $token,
            ]);
        } catch (ExceptionInterface $t) {
            throw new MessageSendFailedException($secretSanta, $secretSanta->getUser($giver), $t);
        }
    }

    /**
     * @throws MessageSendFailedException
     */
    public function sendAdminMessage(SecretSanta $secretSanta, string $code, string $spoilUrl, string $token, string $accountId): void
    {
        $text = sprintf(
            'Dear Secret Santa *admin*,

In case of trouble or if you need it for whatever reason, here is a way to retrieve the secret repartition:

- Copy the following content:

`%s`

- Paste the content on <%s> then submit

Remember, with great power comes great responsibility!

Happy Secret Santa!',
            $code,
            $spoilUrl
        );

        $body = [];
        $body['robot_jid'] = $this->zoomBotJid;
        $body['to_jid'] = self::transformUserIdToJID($secretSanta->getAdmin()->getIdentifier());
        $body['account_id'] = $accountId;

        // todo: Does not work as expected on Linux
        $body['is_markdown_support'] = true;

        $body['content']['body'][] = [
            'type' => 'message',
            'text' => $text,
        ];

        try {
            $this->httpClient->request('POST', 'https://api.zoom.us/v2/im/chat/messages', [
                'json' => $body,
                'auth_bearer' => $token,
            ]);
        } catch (ExceptionInterface $t) {
            throw new MessageSendFailedException($secretSanta, $secretSanta->getAdmin(), $t);
        }
    }

    /**
     * @todo Hack to build the JID. Had a confirmation "I can do it like this".
     */
    private static function transformUserIdToJID(string $userId): string
    {
        return mb_strtolower($userId . '@xmpp.zoom.us');
    }
}
