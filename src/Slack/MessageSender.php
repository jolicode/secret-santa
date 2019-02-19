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
                    ['type' => 'mrkdwn', 'text' => '_Find below a sample of the message that will be sent to each members of your Secret Santa._']
                ]
            ];
        }

        $blocks[] = [
            'type' => 'section',
            'text' => [
                'type' => 'mrkdwn',
                'text' => sprintf('Hi!\nYou have been chosen to be part of a Secret Santa :santa:!\n\n*You have been chosen to gift:*\n\n :point_down: :point_down: :point_down: :point_down: :point_down: :point_down:\n:gift: *<@%s>* :gift:\n:point_up_2: :point_up_2: :point_up_2: :point_up_2: :point_up_2: :point_up_2:', $receiver)
            ],
            'accessory' => [
                'type' => 'image',

                // @todo real data from $secretSanta->getUser() ?
                'image_url' => 'TODO',
                'alt_text' => 'TODO'
            ]
        ];

        $blocks[] = [
            'type' => 'divider'
        ];

        $fallbackText .= sprintf('You have been chosen to be part of a Secret Santa :santa:!
*You have been chosen to gift:* :gift: *<@%s>* :gift:', $receiver);

        if (!empty($secretSanta->getAdminMessage())) {
            $blocks[] = [
                'type' => 'section',
                'text' => [
                    'type' => 'mrkdwn',
                    'text' => '_Here is a message from the Secret Santa admin:_'
                ],
            ];

            $blocks[] = [
                'type' => 'section',
                'text' => [
                    'type' => 'mrkdwn',
                    'text' => $secretSanta->getAdminMessage()
                ],
            ];

            $fallbackText .= "\n\nHere is a message from the Secret Santa admin:\n\n```" . $secretSanta->getAdminMessage() . '```';
        }

        if ($secretSanta->getAdmin()) {
            $blocks[] = [
                'type' => 'section',
                'text' => [
                    'type' => 'mrkdwn',
                    'text' => sprintf('_Your Secret Santa admin, <@%s>._', $secretSanta->getAdmin()->getIdentifier())
                ]
            ];

            $fallbackText .= sprintf("\n\n_Your Secret Santa admin, <@%s>._", $secretSanta->getAdmin()->getIdentifier());
        }

        $blocks[] = [
            'type' => 'context',
            'elements' => [
                ['type' => 'plain_text', 'text' => 'That\'s a secret only shared with you! Someone has also been chosen to get you a gift.'],
                ['type' => 'mrkdwn', 'text' => 'Powered by <https://secret-santa.team/|Secret-Santa.team>']
            ]
        ];

        try {
            $response = $this->clientFactory->getClientForToken($token)->chatPostMessage([
                'channel' => sprintf('@%s', $giver),
                'username' => $isSample ? 'Secret Santa Preview' : 'Secret Santa Bot',
                'icon_url' => 'https://secret-santa.team/images/logo.png',
                'text' => $fallbackText,
                'blocks' => $blocks,
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
