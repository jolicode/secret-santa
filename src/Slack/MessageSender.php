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
                    ['type' => 'mrkdwn', 'text' => '_Find below a sample of the message that will be sent to each members of your Secret Santa._'],
                ],
            ];
        }

        $blocks[] = [
            'type' => 'section',
            'text' => [
                'type' => 'mrkdwn',
                'text' => sprintf("Hi!\nYou have been chosen to be part of a Secret Santa :santa:!\n\n"),
            ],
        ];

        $receiverUser = $secretSanta->getUser($receiver);
        $receiverBlock = [
            'type' => 'section',
            'text' => [
                'type' => 'mrkdwn',
                'text' => sprintf("*You have been chosen to gift:*\n\n:gift: *<@%s>* :gift:\n\n", $receiver),
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

        $fallbackText .= sprintf('You have been chosen to be part of a Secret Santa :santa:!
*You have been chosen to gift:* :gift: *<@%s>* :gift:', $receiver);

        if (!empty($secretSanta->getAdminMessage())) {
            $blocks[] = [
                'type' => 'section',
                'text' => [
                    'type' => 'mrkdwn',
                    'text' => sprintf('*Here is a message from the Secret Santa admin _(<@%s>)_:*', $secretSanta->getAdmin()->getIdentifier()),
                ],
            ];

            $blocks[] = [
                'type' => 'section',
                'text' => [
                    'type' => 'mrkdwn',
                    'text' => $secretSanta->getAdminMessage(),
                ],
            ];

            $fallbackText .= sprintf("\n\nHere is a message from the Secret Santa admin _(<@%s>)_:\n\n```%s```", $secretSanta->getAdmin()->getIdentifier(), $secretSanta->getAdminMessage());
        } else {
            $blocks[] = [
                'type' => 'section',
                'text' => [
                    'type' => 'mrkdwn',
                    'text' => sprintf('_If you have any question please ask your Secret Santa Admin: <@%s>_', $secretSanta->getAdmin()->getIdentifier()),
                ],
            ];
        }

        if (!empty($userNote = $secretSanta->getUserNote($receiver))) {
            $blocks[] = [
                'type' => 'section',
                'text' => [
                    'type' => 'mrkdwn',
                    'text' => sprintf('*Here is some notes about <@%s>:*', $receiver),
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

        $blocks[] = [
            'type' => 'divider',
        ];

        $blocks[] = [
            'type' => 'context',
            'elements' => [
                ['type' => 'plain_text', 'text' => 'That\'s a secret only shared with you! Someone has also been chosen to get you a gift.'],
                ['type' => 'mrkdwn', 'text' => 'Powered by <https://secret-santa.team/|Secret-Santa.team>'],
            ],
        ];

        try {
            $response = $this->clientFactory->getClientForToken($token)->chatPostMessage([
                'channel' => sprintf('@%s', $giver),
                'username' => $isSample ? 'Secret Santa Preview' : 'Secret Santa Bot',
                'icon_url' => 'https://secret-santa.team/images/logo.png',
                'text' => $fallbackText,
                'blocks' => json_encode($blocks),
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
            'Dear Secret Santa admin,

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
                'username' => 'Secret Santa Bot Spoiler',
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
