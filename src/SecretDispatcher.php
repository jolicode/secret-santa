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

    public function dispatchTo($userIds, $adminMessage = null)
    {
        $rudolph = new Rudolph();
        $associatedUsers = $rudolph->associateUsers($userIds);

        foreach ($associatedUsers as $giver => $receiver) {
            $text = sprintf("Hi there, you have been chosen to be part of a Secret Santa!\n
Someone have been chosen to get you a gift; and *you* have been chosen to gift <@%s>!", $receiver);

            if (!empty($adminMessage)) {
                $text .= "\n\nHere is a message from the Secret Santa admin:\n\n> ".strip_tags(str_replace("\n", "", $adminMessage));
            }

            $message = new ChatPostMessagePayload();
            $message->setChannel($giver);
            $message->setText($text);
            $message->setUsername("Secret Santa Bot");
            $message->setIconEmoji(":santa:");

            $this->sendPayload($message);
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
