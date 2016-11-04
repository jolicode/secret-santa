<?php

namespace Joli\SlackSecretSanta;

use CL\Slack\Payload\ChatPostMessagePayload;
use CL\Slack\Payload\PayloadInterface;
use CL\Slack\Transport\ApiClient;

class SecretDispatcher
{
    /** @var ApiClient */
    private $apiClient;

    /**
     * @param ApiClient $apiClient
     */
    public function __construct(ApiClient $apiClient)
    {
        $this->apiClient = $apiClient;
    }

    /**
     * Send messages for remaining associations.
     *
     * This method is limited to 20 seconds to be able to display nice error message instead of being timed out by Heroku.
     *
     * @param SecretSanta $secretSanta
     *
     * @throws \RuntimeException
     */
    public function dispatchRemainingMessages(SecretSanta $secretSanta)
    {
        $startTime = time();

        try {
            foreach ($secretSanta->getRemainingAssociations() as $giver => $receiver) {
                if ((time() - $startTime) > 19) {
                    throw new \RuntimeException('It takes too much time to contact Slack! Please press the Retry button.');
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

                $this->sendPayload($message);

                $secretSanta->markAssociationAsProceeded($giver);
            }
        } catch (\Exception $e) {
            $secretSanta->setError($e->getMessage());
        }
    }

    /**
     * @param PayloadInterface $payload
     *
     * @return \CL\Slack\Payload\PayloadResponseInterface
     */
    private function sendPayload(PayloadInterface $payload)
    {
        $response = $this->apiClient->send($payload);

        if (!$response->isOk()) {
            throw new \RuntimeException(
                sprintf('%s (%s)', $response->getErrorExplanation(), $response->getError())
            );
        }

        return $response;
    }
}
