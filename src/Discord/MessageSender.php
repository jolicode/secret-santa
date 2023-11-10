<?php

/*
 * This file is part of the Secret Santa project.
 *
 * (c) JoliCode <coucou@jolicode.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace JoliCode\SecretSanta\Discord;

use GuzzleHttp\Command\Exception\CommandClientException;
use JoliCode\SecretSanta\Exception\MessageSendFailedException;
use JoliCode\SecretSanta\Model\SecretSanta;

class MessageSender
{
    public function __construct(private ApiHelper $apiHelper)
    {
    }

    /**
     * @throws MessageSendFailedException
     */
    public function sendSecretMessage(SecretSanta $secretSanta, string $giver, string $receiver, bool $isSample): void
    {
        $text = '';

        if ($isSample) {
            $text .= "_Find below a **sample** of the message that will be sent to all participants of your Secret Santa._\n----\n\n";
        }

        $receiverUser = $secretSanta->getUser($receiver);

        $text .= sprintf(
            'Hi!

You have been selected to be part of a Secret Santa :santa:!

Someone will get you a gift and **you have been chosen to gift:**
:gift: **%s (<@!%s>)** :gift:',
            $receiverUser->getExtra()['nickname'] ?? $receiverUser->getName(),
            $receiver
        );

        if (!empty($userNote = $secretSanta->getUserNote($receiver))) {
            // The extra space after the last %s seems mandatory to not break message in Discord mobile application
            $text .= sprintf("\n\nHere is some details about %s:\n\n```%s ```", $receiverUser->getName(), $userNote);
        }

        if (!empty($secretSanta->getAdminMessage())) {
            $text .= "\n\nHere is a message from the Secret Santa admin:\n\n```\n" . $secretSanta->getAdminMessage() . "\n```";
        } else {
            $text .= "\n\nIf you have any question please ask your Secret Santa admin";
        }

        $text .= "\n\n_Organized with Secret-Santa.team";

        if ($admin = $secretSanta->getConfig()->getAdmin()) {
            $text .= sprintf(' by admin %s (<@!%s>)._', $admin->getExtra()['nickname'] ?? $admin->getName(), $admin->getIdentifier());
        } else {
            $text .= '_';
        }

        $text .= "\n\n";
        $text .= '_Note: if you see `@invalid-user` as the user you need to send a gift, please read this message from desktop Discord application. There is a known bug in Discord Mobile applications._';

        try {
            $this->apiHelper->sendMessage((int) $giver, $text);
        } catch (CommandClientException $e) {
            $precision = null;

            if (($response = $e->getResponse()) && 403 === $response->getStatusCode()) {
                $precision = sprintf(
                    '@%s does not allow to receive DM on the server "%s". Please ask them to change their server privacy settings as explained in our faq.',
                    $secretSanta->getUser($giver)->getName(),
                    $secretSanta->getConfig()->getOrganization()
                );
            }

            throw new MessageSendFailedException($secretSanta, $secretSanta->getUser($giver), $e, $precision);
        } catch (\Throwable $t) {
            throw new MessageSendFailedException($secretSanta, $secretSanta->getUser($giver), $t);
        }
    }

    /**
     * @throws MessageSendFailedException
     */
    public function sendAdminMessage(SecretSanta $secretSanta, string $code, string $spoilUrl): void
    {
        $text = sprintf(
            'Dear Secret Santa **admin**,

In case of trouble or if you need it for whatever reason, here is a way to **retrieve the secret repartition**:

- Copy the following content:
```%s```
- Paste the content on <%s> then submit

Remember, with great power comes great responsibility!

Happy Secret Santa!',
            $code,
            $spoilUrl
        );

        try {
            $this->apiHelper->sendMessage((int) $secretSanta->getConfig()->getAdmin()->getIdentifier(), $text);
        } catch (CommandClientException $e) {
            $precision = null;

            if (($response = $e->getResponse()) && 403 === $response->getStatusCode()) {
                $precision = 'You do not allow to receive DM on this server. Please change your server settings.';
            }

            throw new MessageSendFailedException($secretSanta, $secretSanta->getConfig()->getAdmin(), $e, $precision);
        } catch (\Throwable $t) {
            throw new MessageSendFailedException($secretSanta, $secretSanta->getConfig()->getAdmin(), $t);
        }
    }
}
