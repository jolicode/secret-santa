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

use AdamPaterson\OAuth2\Client\Provider\Slack;
use League\OAuth2\Client\Token\AccessToken;

/**
 * Overrides Slack provider while https://github.com/adam-paterson/oauth2-slack/pull/25
 * is not merged.
 */
class SlackProvider extends Slack
{
    private const SCOPES = [
        'channels:read',
        'chat:write',
        'groups:read',
        'im:write',
        'team:read',
        'usergroups:read',
        'users:read',
    ];

    public function getBaseAuthorizationUrl(): string
    {
        return 'https://slack.com/oauth/v2/authorize';
    }

    /**
     * @param array<string,mixed> $params
     */
    public function getBaseAccessTokenUrl(array $params): string
    {
        return 'https://slack.com/api/oauth.v2.access';
    }

    /**
     * Slack can grant fewer scopes than requested, for instance when the workspace
     * approved an older version of the app and its new permissions are pending.
     *
     * @return list<string>
     */
    public function getMissingScopes(AccessToken $token): array
    {
        return array_values(array_diff(self::SCOPES, explode(',', $token->getValues()['scope'])));
    }

    /**
     * @return list<string>
     */
    protected function getDefaultScopes(): array
    {
        return self::SCOPES;
    }
}
