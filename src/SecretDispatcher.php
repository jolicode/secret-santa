<?php

/*
 * This file is part of the Slack Secret Santa project.
 *
 * (c) JoliCode <coucou@jolicode.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Joli\SlackSecretSanta;

use CL\Slack\Payload\ChatPostMessagePayload;
use CL\Slack\Payload\PayloadInterface;
use CL\Slack\Payload\PayloadResponseInterface;
use CL\Slack\Transport\ApiClient;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

class SecretDispatcher
{
    /** @var ApiClient */
    private $apiClient;

    /** @var Spoiler */
    private $spoiler;

    /** @var UrlGeneratorInterface */
    private $urlGenerator;

    public function __construct(ApiClient $apiClient, UrlGeneratorInterface $urlGenerator, Spoiler $spoiler)
    {
        $this->apiClient = $apiClient;
        $this->spoiler = $spoiler;
        $this->urlGenerator = $urlGenerator;
    }

    /**
     * Send messages for remaining associations.
     *
     * This method is limited to 20 seconds to be able to display nice error message instead of being timed out by Heroku.
     *
     * @throws \RuntimeException
     */
    public function dispatchRemainingMessages(SecretSanta $secretSanta, string $token): void
    {
        $startTime = time();

        try {
            foreach ($secretSanta->getRemainingAssociations() as $giver => $receiver) {
                if ((time() - $startTime) > 19) {
                    throw new \RuntimeException('It takes too much time to contact Slack!');
                }

                $text = sprintf("Hi! You have been chosen to be part of a Secret Santa!\n
Someone has been chosen to get you a gift; and *you* have been chosen to gift <@%s>!", $receiver);

                if (!empty($secretSanta->getAdminMessage())) {
                    $text .= "\n\nHere is a message from the Secret Santa admin:\n\n```" . $secretSanta->getAdminMessage() . '```';
                }

                if ($secretSanta->getAdminUserId()) {
                    $text .= sprintf("\n\nMessage sent via <@%s>.", $secretSanta->getAdminUserId());
                }

                $message = new ChatPostMessagePayload();
                $message->setChannel(sprintf('@%s', $giver));
                $message->setText($text);
                $message->setUsername('Secret Santa Bot');
                $message->setIconUrl('https://slack-secret-santa.herokuapp.com/images/logo.png');

                $this->sendPayload($message, $token);

                $secretSanta->markAssociationAsProceeded($giver);
            }

            // Send a summary to the santa admin
            if ($secretSanta->getAdminUserId()) {
                $text = sprintf(
'Dear santa admin,

In case of trouble or if you need it for whatever reason, here is a way to retrieve the secret repartition:

- Copy the following content:
```%s```
- Paste the content on <%s|this page> then submit

Remember, with great power comes great responsibility!

Happy secret santa!',
                    $this->spoiler->encode($secretSanta),
                    $this->urlGenerator->generate('spoil', [], UrlGeneratorInterface::ABSOLUTE_URL)
                );

                $message = new ChatPostMessagePayload();
                $message->setChannel($secretSanta->getAdminUserId());
                $message->setText($text);
                $message->setUsername('Secret Santa Bot Spoiler');
                $message->setIconUrl('https://slack-secret-santa.herokuapp.com/images/logo-spoiler.png');

                $this->sendPayload($message, $token);
            }
        } catch (\Throwable $t) {
            $secretSanta->addError($t->getMessage());
        }
    }

    private function sendPayload(PayloadInterface $payload, string $token): PayloadResponseInterface
    {
        $response = $this->apiClient->send($payload, $token);

        if (!$response->isOk()) {
            throw new \RuntimeException(
                sprintf('%s (%s)', $response->getErrorExplanation(), $response->getError())
            );
        }

        return $response;
    }
}
