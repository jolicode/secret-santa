<?php

/*
 * This file is part of the Secret Santa project.
 *
 * (c) JoliCode <coucou@jolicode.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace JoliCode\SecretSanta\Tests\Fake;

use Symfony\Contracts\HttpClient\ResponseInterface;

/**
 * Routes each HTTP request of the test env to the fake of the chat service it targets.
 */
final class FakeChatApiDispatcher
{
    public function __construct(
        private FakeSlackApi $slack,
        private FakeDiscordApi $discord,
        private FakeWebexApi $webex,
    ) {
    }

    /**
     * @param array<string, mixed> $options
     */
    public function __invoke(string $method, string $url, array $options): ResponseInterface
    {
        $api = match (parse_url($url, \PHP_URL_HOST)) {
            'slack.com' => $this->slack,
            'discord.com' => $this->discord,
            'webexapis.com' => $this->webex,
            default => throw new \LogicException('No fake API for ' . $url),
        };

        return $api($method, $url, $options);
    }
}
