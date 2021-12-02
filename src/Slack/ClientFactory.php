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

use JoliCode\Slack\Api\Client;
use JoliCode\Slack\ClientFactory as DefaultClientFactory;
use Psr\Http\Client\ClientInterface as PsrHttpClient;

class ClientFactory
{
    /** @var array<string, Client> */
    private array $clientsByToken = [];

    public function __construct(private PsrHttpClient $httpClient)
    {
    }

    public function getClientForToken(string $token): Client
    {
        if (!isset($this->clientsByToken[$token])) {
            $this->clientsByToken[$token] = DefaultClientFactory::create($token, $this->httpClient);
        }

        return $this->clientsByToken[$token];
    }
}
