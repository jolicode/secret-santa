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
     * This method is limited to 20 seconds because we are limited on time by Heroku
     *
     * @param string[]    $userIds
     * @param string|null $adminMessage
     * @param null        $adminUserId
     *
     * @return Result
     */
    public function dispatchTo($userIds, $adminMessage = null, $adminUserId = null)
    {
        $startTime = time();
        $rudolph = new Rudolph();
        $associatedUsers = $rudolph->associateUsers($userIds);
        $hash = md5(serialize($associatedUsers));
        $remainingAssociations = $associatedUsers;
        $error = null;

        try {
            foreach ($associatedUsers as $giver => $receiver) {
                if ((time() - $startTime) > 19) {
                    throw new \Exception("It takes too much time to contact Slack! Please press the Retry button.");
                }

                $text = sprintf("Hi! You have been chosen to be part of a Secret Santa!\n
Someone has been chosen to get you a gift; and *you* have been chosen to gift <@%s>!", $receiver);

                if (!empty($adminMessage)) {
                    $text .= "\n\nHere is a message from the Secret Santa admin:\n\n```" . strip_tags($adminMessage) . '```';
                }

                if ($adminUserId) {
                    $text .= sprintf("\n\nMessage sent via <@%s>.", $adminUserId);
                }

                $message = new ChatPostMessagePayload();
                $message->setChannel(sprintf('@%s', $giver));
                $message->setText($text);
                $message->setUsername('Secret Santa Bot');
                $message->setIconUrl('https://slack-secret-santa.herokuapp.com/images/logo.png');

                $this->sendPayload($message);

                unset($remainingAssociations[$giver]);
            }
        } catch (\Exception $e) {
            $error = $e->getMessage();
        }

        return new Result($hash, $associatedUsers, $remainingAssociations, $error);
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
