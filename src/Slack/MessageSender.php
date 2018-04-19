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

use CL\Slack\Payload\ChatPostMessagePayload;
use JoliCode\SecretSanta\Exception\MessageSendFailedException;
use JoliCode\SecretSanta\SecretSanta;

class MessageSender
{
    private $slackService;

    public function __construct(SlackService $slackService)
    {
        $this->slackService = $slackService;
    }

    /**
     * @throws MessageSendFailedException
     */
    public function sendSecretMessage(SecretSanta $secretSanta, string $giver, string $receiver, string $token): void
    {
        $text = sprintf(
'Hi! You have been chosen to be part of a Secret Santa!

Someone has been chosen to get you a gift; and *you* have been chosen to gift <@%s>!', $receiver);

        if (!empty($secretSanta->getAdminMessage())) {
            $text .= "\n\nHere is a message from the Secret Santa admin:\n\n```" . $secretSanta->getAdminMessage() . '```';
        }

        if ($secretSanta->getAdmin()) {
            $text .= sprintf("\n\nYour Secret Santa admin, <@%s>.", $secretSanta->getAdmin()->getIdentifier());
        }

        $message = new ChatPostMessagePayload();
        $message->setChannel(sprintf('@%s', $giver));
        $message->setText($text);
        $message->setUsername('Secret Santa Bot');
        $message->setIconUrl('https://secret-santa.team/images/logo.png');

        try {
            $this->slackService->sendPayload($message, $token);
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

        $message = new ChatPostMessagePayload();
        $message->setChannel($secretSanta->getAdmin()->getIdentifier());
        $message->setText($text);
        $message->setUsername('Secret Santa Bot Spoiler');
        $message->setIconUrl('https://secret-santa.team/images/logo-spoiler.png');

        try {
            $this->slackService->sendPayload($message, $token);
        } catch (\Throwable $t) {
            throw new MessageSendFailedException($secretSanta, $secretSanta->getAdmin(), $t);
        }
    }
}
