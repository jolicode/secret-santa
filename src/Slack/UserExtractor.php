<?php

/*
 * This file is part of the Slack Secret Santa project.
 *
 * (c) JoliCode <coucou@jolicode.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Joli\SlackSecretSanta\Slack;

use CL\Slack\Model\User as SlackUser;
use CL\Slack\Payload\PayloadInterface;
use CL\Slack\Payload\PayloadResponseInterface;
use CL\Slack\Payload\UsersListPayload;
use CL\Slack\Payload\UsersListPayloadResponse;
use CL\Slack\Transport\ApiClient;
use Joli\SlackSecretSanta\User;

class UserExtractor
{
    /** @var ApiClient */
    private $apiClient;

    public function __construct(ApiClient $apiClient)
    {
        $this->apiClient = $apiClient;
    }

    public function extractAll(string $token): array
    {
        $payload = new UsersListPayload();

        /** @var $response UsersListPayloadResponse */
        $response = $this->sendPayload($payload, $token);

        /** @var SlackUser[] $slackUsers */
        $slackUsers = array_filter($response->getUsers(), function (SlackUser $user) {
            return
                !$user->isBot()
                && !$user->isDeleted()
                && 'slackbot' !== $user->getName()
            ;
        });

        $users = [];

        foreach ($slackUsers as $slackUser) {
            $user = new User(
                $slackUser->getId(),
                $slackUser->getProfile()->getRealName(),
                [
                    'nickname' => $slackUser->getName(),
                    'image' => $slackUser->getProfile()->getImage32(),
                    'restricted' => $slackUser->isRestricted(),
                ]
            );

            $users[$user->getIdentifier()] = $user;
        }

        uasort($users, function (User $a, User $b) {
            return strnatcasecmp($a->getName(), $b->getName());
        });

        return $users;
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
