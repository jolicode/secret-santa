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
use Symfony\Contracts\HttpClient\HttpClientInterface;

class MessageSender
{
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

        // todo: Does not work as expected
        $body['is_markdown_support'] = true;

        // Doc: https://marketplace.zoom.us/docs/guides/chatbots/customizing-messages/message-with-markdown
        $body['content'] = [
            'body' => [
                [
                    'type' => 'message',
                    'text' => "Hi!\nYou have been *chosen* to be part of a Secret Santa &#127877;!\n\n",
                ],
            ],
        ];

        if ($isSample) {
            $body['content']['head'] = [
                'type' => 'message',
                'text' => '_Find below a sample of the message that will be sent to each members of your Secret Santa._',
            ];
        }

        //$receiverUser = $secretSanta->getUser($receiver);
        $body['content']['body'][] = [
            'type' => 'message',
            'text' => sprintf("*You have been chosen to gift:*\n\n:gift: <!%s|Name Here> :gift:\n\n", $receiver),
        ];

        if (!empty($secretSanta->getAdminMessage())) {
            $body['content']['body'][] = [
                'type' => 'message',
                'text' => sprintf('*Here is a message from the Secret Santa admin (<!%s>):*', $secretSanta->getAdmin()->getIdentifier()),
            ];

            $body['content']['body'][] = [
                'type' => 'message',
                'text' => $secretSanta->getAdminMessage(),
            ];
        } else {
            $body['content']['body'][] = [
                'type' => 'message',
                'text' => sprintf('_If you have any question please ask your Secret Santa Admin: <!%s>_', $secretSanta->getAdmin()->getIdentifier()),
            ];
        }

        $body['content']['body'][] = [
            'type' => 'message',
            'text' => 'That\'s a secret only shared with you! Someone has also been chosen to get you a gift.',
            'italic' => true,
            //'link' => 'https://secret-santa.team/', // todo: thats broken.
        ];

        try {
            $this->httpClient->request('POST', 'https://api.zoom.us/v2/im/chat/messages', [
                'json' => $body,
                'auth_bearer' => $token,
            ]);
        } catch (\Throwable $t) {
            throw new MessageSendFailedException($secretSanta, $secretSanta->getUser($giver), $t);
        }
    }

    /**
     * @throws MessageSendFailedException
     */
    public function sendAdminMessage(SecretSanta $secretSanta, string $code, string $spoilUrl, string $token, string $accountId): void
    {
        $text = sprintf(
            'Dear Secret Santa admin,

In case of trouble or if you need it for whatever reason, here is a way to retrieve the secret repartition:

- Copy the following content:
```%s```
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

        // todo: Does not work as expected
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
        } catch (\Throwable $t) {
            throw new MessageSendFailedException($secretSanta, $secretSanta->getAdmin(), $t);
        }
    }

    /**
     * @todo I have no idea why I should do this or if this is secure. The "contacts" API does not return JID, and JID are mandatory to send messages.
     */
    private static function transformUserIdToJID(string $userId)
    {
        return mb_strtolower($userId . '@xmpp.zoom.us');
    }
}