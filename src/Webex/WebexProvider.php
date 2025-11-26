<?php

/*
 * This file is part of the Secret Santa project.
 *
 * (c) JoliCode <coucou@jolicode.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace JoliCode\SecretSanta\Webex;

use League\OAuth2\Client\Provider\AbstractProvider;
use League\OAuth2\Client\Provider\Exception\IdentityProviderException;
use League\OAuth2\Client\Provider\ResourceOwnerInterface;
use League\OAuth2\Client\Token\AccessToken;
use Psr\Http\Message\ResponseInterface;

class WebexProvider extends AbstractProvider
{
    public function getBaseAuthorizationUrl(): string
    {
        return 'https://webexapis.com/v1/authorize';
    }

    /**
     * @param array<mixed> $params
     */
    public function getBaseAccessTokenUrl(array $params): string
    {
        return 'https://webexapis.com/v1/access_token';
    }

    public function getResourceOwnerDetailsUrl(AccessToken $token): string
    {
        throw new \Exception('Not implemented.');
    }

    protected function getScopeSeparator(): string
    {
        return ' ';
    }

    /**
     * @return string[]
     */
    protected function getDefaultScopes(): array
    {
        return ['spark:kms'];
    }

    /**
     * @param mixed $data Parsed response data
     */
    protected function checkResponse(ResponseInterface $response, $data): void
    {
        if ($response->getStatusCode() >= 400) {
            throw new IdentityProviderException('Invalid response from Webex', $response->getStatusCode(), $response->getBody()->getContents());
        }
    }

    /**
     * @param array<mixed> $response
     */
    protected function createResourceOwner(array $response, AccessToken $token): ResourceOwnerInterface
    {
        throw new \Exception('Not implemented.');
    }
}
