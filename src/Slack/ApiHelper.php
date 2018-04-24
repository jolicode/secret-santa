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

use CL\Slack\Payload\PayloadInterface;
use CL\Slack\Payload\PayloadResponseInterface;
use CL\Slack\Transport\ApiClient;

class ApiHelper
{
    /** @var ApiClient */
    private $apiClient;

    public function __construct(ApiClient $apiClient)
    {
        $this->apiClient = $apiClient;
    }

    public function sendPayload(PayloadInterface $payload, string $token): PayloadResponseInterface
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
