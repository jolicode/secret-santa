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

use JoliCode\SecretSanta\Exception\UserExtractionFailedException;
use JoliCode\SecretSanta\Utils\LongTask;
use JoliCode\Slack\Api\Model\ObjsUser;

class UserFetchTask extends LongTask
{
    private $clientFactory;

    private $token;

    /** @var ObjsUser[] */
    private $slackUsers = [];

    public function __construct(ClientFactory $clientFactory)
    {
        $this->clientFactory = $clientFactory;
    }

    public function setToken(string $token)
    {
        $this->token = $token;
    }

    protected function init()
    {
        if (!$this->token) {
            throw new \LogicException('Missing token to start the task.');
        }

        $this->slackUsers = [];
    }

    protected function getInitialValue()
    {
        return '';
    }

    protected function getResult()
    {
        return $this->slackUsers;
    }

    protected function iterate($value)
    {
        try {
            $response = $this->clientFactory->getClientForToken($this->token)->usersList([
                'limit' => 200,
                'cursor' => $value,
            ]);

            if (!$response->getOk()) {
                throw new UserExtractionFailedException('Could not fetch members in team.');
            }
        } catch (\Throwable $t) {
            throw new UserExtractionFailedException('Could not fetch members in team.', 0, $t);
        }

        $this->slackUsers = array_merge($this->slackUsers, $response->getMembers());

        return $response->getResponseMetadata() ? $response->getResponseMetadata()->getNextCursor() : '';
    }

    protected function shouldContinue($value): bool
    {
        return !empty($value);
    }

    protected function onTimeOut()
    {
        throw new UserExtractionFailedException('Took too much time to retrieve all the users on your team.');
    }
}
