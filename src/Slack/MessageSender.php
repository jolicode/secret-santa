<?php

/*
 * This file is part of the Secret Santa project.
 *
 * (c) JoliCode <coucou@jolicode.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace JoliCode\SecretSanta\Slack;

use JoliCode\SecretSanta\Exception\MessageSendFailedException;
use JoliCode\SecretSanta\Model\SecretSanta;

class MessageSender
{
    private $clientFactory;

    public function __construct(ClientFactory $clientFactory)
    {
        $this->clientFactory = $clientFactory;
    }

    /**
     * @throws MessageSendFailedException
     */
    public function sendSecretMessage(SecretSanta $secretSanta, string $giver, string $receiver, string $token, bool $isSample): void
    {
        $fallbackText = '';
        $blocks = [];

        if ($isSample) {
            $blocks[] = [
                'type' => 'context',
                'elements' => [
                    ['type' => 'mrkdwn', 'text' => '_Find below a *sample* of the message that will be sent to all participants of your Secret Santa._'],
                ],
            ];

            $blocks[] = [
                'type' => 'divider',
            ];
        }

        $blocks[] = [
            'type' => 'section',
            'text' => [
                'type' => 'mrkdwn',
                'text' => "Hi!\nYou have been selected to be part of a Secret Santa :santa:!\n\n",
            ],
        ];

        $receiverUser = $secretSanta->getUser($receiver);
        $receiverBlock = [
            'type' => 'section',
            'text' => [
                'type' => 'mrkdwn',
                'text' => sprintf("Someone will get you a gift and *you have been chosen to gift:*\n\n:gift: *<@%s>* :gift:\n\n", $receiver),
            ],
        ];

        if ($receiverUser->getExtra() && \array_key_exists('image', $receiverUser->getExtra())) {
            $receiverBlock['accessory'] = [
                'type' => 'image',
                'image_url' => $receiverUser->getExtra()['image'],
                'alt_text' => $receiverUser->getName(),
            ];
        }

        $blocks[] = $receiverBlock;

        $fallbackText .= sprintf('You have been selected to be part of a Secret Santa :santa:!
*You have been chosen to gift:* :gift: *<@%s>* :gift:', $receiver);

        if (!empty($userNote = $secretSanta->getUserNote($receiver))) {
            $blocks[] = [
                'type' => 'section',
                'text' => [
                    'type' => 'mrkdwn',
                    'text' => sprintf('*Here is some details about <@%s>:*', $receiver),
                ],
            ];

            $blocks[] = [
                'type' => 'section',
                'text' => [
                    'type' => 'mrkdwn',
                    'text' => $userNote,
                ],
            ];
        }

        if (!empty($secretSanta->getAdminMessage())) {
            $blocks[] = [
                'type' => 'section',
                'text' => [
                    'type' => 'mrkdwn',
                    'text' => '*Here is a message from the Secret Santa admin:*',
                ],
            ];

            $blocks[] = [
                'type' => 'section',
                'text' => [
                    'type' => 'mrkdwn',
                    'text' => $secretSanta->getAdminMessage(),
                ],
            ];

            $fallbackText .= sprintf("\n\nHere is a message from the Secret Santa admin:\n\n```%s```", $secretSanta->getAdminMessage());
        } else {
            $blocks[] = [
                'type' => 'section',
                'text' => [
                    'type' => 'mrkdwn',
                    'text' => 'If you have any question please ask your Secret Santa admin',
                ],
            ];
        }

        $blocks[] = [
            'type' => 'divider',
        ];

        $footer = 'Organized with <https://secret-santa.team/|Secret-Santa.team>';

        if ($admin = $secretSanta->getAdmin()) {
            $footer .= sprintf(' by admin <@%s>.', $admin->getIdentifier());
        }

        $blocks[] = [
            'type' => 'context',
            'elements' => [
                [
                    'type' => 'mrkdwn',
                    'text' => $footer,
                ],
            ],
        ];

        try {
            $response = $this->clientFactory->getClientForToken($token)->chatPostMessage([
                'channel' => $giver,
                'icon_url' => 'https://secret-santa.team/images/logo.png',
                'text' => $fallbackText,
                'blocks' => json_encode($blocks),
                'unfurl_links' => false,
                'unfurl_media' => false,
            ]);

            if (!$response->getOk()) {
                throw new MessageSendFailedException($secretSanta, $secretSanta->getUser($giver));
            }
        } catch (\Throwable $t) {
            throw new MessageSendFailedException($secretSanta, $secretSanta->getUser($giver), $t);
        }
    }

    /**
     * @throws MessageSendFailedException
     */
    public function sendAdminMessage(SecretSanta $secretSanta, string $code, string $spoilUrl, string $token): void
    {
        $text = sprintf(
            'Dear Secret Santa *admin*,

In case of trouble or if you need it for whatever reason, here is a way to retrieve the secret repartition:

- Copy the following content:
```%s```
- Paste the content on <%s|this page> then submit

Remember, with great power comes great responsibility!

Happy Secret Santa!',
            $code,
            $spoilUrl
        );

        try {
            $response = $this->clientFactory->getClientForToken($token)->chatPostMessage([
                'channel' => $secretSanta->getAdmin()->getIdentifier(),
                'icon_url' => 'https://secret-santa.team/images/logo-spoiler.png',
                'text' => $text,
            ]);

            if (!$response->getOk()) {
                throw new MessageSendFailedException($secretSanta, $secretSanta->getAdmin());
            }
        } catch (\Throwable $t) {
            throw new MessageSendFailedException($secretSanta, $secretSanta->getAdmin(), $t);
        }
    }
}
