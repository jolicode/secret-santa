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

use Symfony\Component\HttpClient\Response\MockResponse;
use Symfony\Contracts\HttpClient\ResponseInterface;

/**
 * Answers the chat API calls made by the application in the test env, in place of the real service.
 *
 * Plugged behind the "mock_response_factory" of the framework HTTP client, so the real API
 * clients, serialization and error handling of the application are exercised.
 */
abstract class FakeChatApi
{
    /** @var list<array{method: string, path: string, query: array<string, mixed>, body: string}> */
    public array $requests = [];

    /** @var list<string> Ids of the users the chat refuses to deliver messages to */
    public array $failingUsers = [];

    /**
     * @param array<string, mixed> $options The request options, as normalized by the HTTP client
     */
    public function __invoke(string $method, string $url, array $options): ResponseInterface
    {
        parse_str((string) parse_url($url, \PHP_URL_QUERY), $query);
        $body = \is_string($options['body'] ?? null) ? $options['body'] : '';
        // The Discord client builds URLs with a double slash, which the real API tolerates
        $path = (string) preg_replace('{/+}', '/', (string) parse_url($url, \PHP_URL_PATH));

        $this->requests[] = ['method' => $method, 'path' => $path, 'query' => $query, 'body' => $body];

        return $this->answer($method, $path, $query, $body, $options);
    }

    /**
     * @return list<array{to: string, text: string, ...}> The messages sent to users, in sending order, with
     *                                                    their textual content and the id of the recipient
     */
    abstract public function postedMessages(): array;

    /**
     * @param array<string, mixed> $query
     * @param array<string, mixed> $options
     */
    abstract protected function answer(string $method, string $path, array $query, string $body, array $options): ResponseInterface;

    /**
     * @param array<mixed> $data
     */
    protected function json(array $data, int $status = 200): MockResponse
    {
        return new MockResponse((string) json_encode($data), [
            'http_code' => $status,
            'response_headers' => ['content-type' => 'application/json'],
        ]);
    }
}
