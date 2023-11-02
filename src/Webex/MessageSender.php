<?php

/*
 * This file is part of the Secret Santa project.
 *
 * (c) JoliCode <coucou@jolicode.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace JoliCode\SecretSanta\Webex;

use JoliCode\SecretSanta\Exception\MessageSendFailedException;
use JoliCode\SecretSanta\Model\SecretSanta;
use Symfony\Contracts\HttpClient\HttpClientInterface;

class MessageSender
{
    public function __construct(readonly private HttpClientInterface $client, readonly private string $webexBotToken)
    {
    }

    public function sendSecretMessage(SecretSanta $secretSanta, string $giver, string $receiver, bool $isSample): void
    {
        $text = '';

        if ($isSample) {
            $text .= "_Find below a **sample** of the message that will be sent to all participants of your Secret Santa._\n\n----\n\n";
        }

        $receiverUser = $secretSanta->getUser($receiver);

        $text .= sprintf(
            'Hi!

You have been selected to be part of a Secret Santa 🎅!

Someone will get you a gift and **you have been chosen to gift:**

🎁 **%s** 🎁',
            $receiverUser->getName()
        );

        if (!empty($userNote = $secretSanta->getUserNote($receiver))) {
            $text .= sprintf("\n\nHere is some details about %s:\n\n```\n%s\n```", $receiverUser->getName(), $userNote);
        }

        if (!empty($secretSanta->getAdminMessage())) {
            $text .= "\n\nHere is a message from the Secret Santa admin:\n\n```" . $secretSanta->getAdminMessage() . "\n```";
        } else {
            $text .= "\n\nIf you have any question please ask your Secret Santa admin";
        }

        $text .= "\n\n_Organized with Secret-Santa.team";

        if ($admin = $secretSanta->getConfig()->getAdmin()) {
            $text .= sprintf(' by admin %s._', $admin->getName());
        } else {
            $text .= '_';
        }

        $messageSend = $this->client->request('POST', 'https://webexapis.com/v1/messages', [
            'auth_bearer' => $this->webexBotToken,
            'headers' => [
                'accept' => 'application/json',
            ],
            'json' => [
                'toPersonId' => $giver,
                'markdown' => $text,
            ],
        ]);

        if (200 === $messageSend->getStatusCode()) {
            return;
        }

        throw new MessageSendFailedException($secretSanta, $secretSanta->getUser($giver));
    }

    public function sendAdminMessage(SecretSanta $secretSanta, string $code, string $spoilUrl): void
    {
        $text = sprintf(
            'Dear Secret Santa **admin**,

In case of trouble or if you need it for whatever reason, here is a way to **retrieve the secret repartition**:

- Copy the following content:
```%s```
- Paste the content on %s then submit

Remember, with great power comes great responsibility!

Happy Secret Santa!',
            $code,
            $spoilUrl
        );

        $messageSend = $this->client->request('POST', 'https://webexapis.com/v1/messages', [
            'auth_bearer' => $this->webexBotToken,
            'headers' => [
                'accept' => 'application/json',
            ],
            'json' => [
                'toPersonId' => $secretSanta->getConfig()->getAdmin()->getIdentifier(),
                'markdown' => $text,
            ],
        ]);

        if (200 === $messageSend->getStatusCode()) {
            return;
        }

        throw new MessageSendFailedException($secretSanta, $secretSanta->getConfig()->getAdmin());
    }
}
