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
use JoliCode\SecretSanta\SecretSanta;

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
        $text = '';

        if ($isSample) {
            $text .= "_Find below a sample of the message that will be sent to each members of your Secret Santa._\n----\n\n";
        }

        $text .= sprintf(
'Hi! You have been chosen to be part of a Secret Santa!

Someone has been chosen to get you a gift; and *you* have been chosen to gift <@%s>!', $receiver);

        if (!empty($secretSanta->getAdminMessage())) {
            $text .= "\n\nHere is a message from the Secret Santa admin:\n\n```" . $secretSanta->getAdminMessage() . '```';
        }

        if ($secretSanta->getAdmin()) {
            $text .= sprintf("\n\nYour Secret Santa admin, <@%s>.", $secretSanta->getAdmin()->getIdentifier());
        }

        try {
            $this->clientFactory->getClientForToken($token)->chatPostMessage([
                'channel' => sprintf('@%s', $giver),
                'username' => $isSample ? 'Secret Santa Preview' : 'Secret Santa Bot',
                'icon_url' => 'https://secret-santa.team/images/logo.png',
                'text' => $text,
            ]);
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
            $this->clientFactory->getClientForToken($token)->chatPostMessage([
                'channel' => $secretSanta->getAdmin()->getIdentifier(),
                'username' => 'Secret Santa Bot Spoiler',
                'icon_url' => 'https://secret-santa.team/images/logo-spoiler.png',
                'text' => $text,
            ]);
        } catch (\Throwable $t) {
            throw new MessageSendFailedException($secretSanta, $secretSanta->getAdmin(), $t);
        }
    }
}
