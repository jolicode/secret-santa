<?php

/*
 * This file is part of the Secret Santa project.
 *
 * (c) JoliCode <coucou@jolicode.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace JoliCode\SecretSanta\Microsoft;

use JoliCode\SecretSanta\Exception\MessageSendFailedException;
use JoliCode\SecretSanta\SecretSanta;

class MessageSender
{
    /** @var ApiHelper */
    private $apiHelper;

    public function __construct(ApiHelper $apiHelper)
    {
        $this->apiHelper = $apiHelper;
    }

    /**
     * @throws MessageSendFailedException
     */
    public function sendSecretMessage(SecretSanta $secretSanta, string $giver, string $receiver, Configuration $configuration)
    {
        $text = sprintf(
'Hi! You have been chosen to be part of a Secret Santa!

Someone has been chosen to get you a gift; and **you** have been chosen to gift <@%s>!', $receiver);

        if (!empty($secretSanta->getAdminMessage())) {
            $text .= "\n\nHere is a message from the Secret Santa admin:\n\n```" . $secretSanta->getAdminMessage() . '```';
        }

        if ($secretSanta->getAdmin()) {
            $text .= sprintf("\n\nYour Secret Santa admin, <@%s>.", $secretSanta->getAdmin()->getIdentifier());
        }

        try {
            $this->apiHelper->sendMessage($configuration, $giver, $text);
        } catch (\Throwable $t) {
            throw new MessageSendFailedException($secretSanta, $secretSanta->getUser($giver), $t);
        }
    }

    /**
     * @throws MessageSendFailedException
     */
    public function sendAdminMessage(SecretSanta $secretSanta, string $code, string $spoilUrl, Configuration $configuration): void
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

        try {
            $this->apiHelper->sendMessage($configuration, $secretSanta->getAdmin()->getIdentifier(), $text);
        } catch (\Throwable $t) {
            throw new MessageSendFailedException($secretSanta, $secretSanta->getAdmin(), $t);
        }
    }
}
