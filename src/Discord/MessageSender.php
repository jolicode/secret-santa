<?php

/*
 * This file is part of the Slack Secret Santa project.
 *
 * (c) JoliCode <coucou@jolicode.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Joli\SlackSecretSanta\Discord;

use Joli\SlackSecretSanta\SecretSanta;

class MessageSender
{
    /** @var DiscordService */
    private $discordService;

    public function __construct(DiscordService $discordService)
    {
        $this->discordService = $discordService;
    }

    public function sendSecretMessage(SecretSanta $secretSanta, string $giver, string $receiver): void
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

        $this->discordService->sendMessage($giver, $text);
    }

    public function sendAdminMessage(SecretSanta $secretSanta, string $code, string $spoilUrl): void
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

        $this->discordService->sendMessage($secretSanta->getAdmin()->getIdentifier(), $text);
    }
}
