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
 * Webex API: the rooms of the admin, their memberships, and direct messages.
 */
final class FakeWebexApi extends FakeChatApi
{
    private const PEOPLE = ['W1' => 'Alice', 'W2' => 'Bob', 'W3' => 'Carol', 'W4' => 'Dave', 'W5' => 'Eve'];

    public function postedMessages(): array
    {
        $messages = [];
        foreach ($this->requests as $request) {
            if ('/v1/messages' !== $request['path']) {
                continue;
            }
            $body = json_decode($request['body'], true);
            $messages[] = ['to' => $body['toPersonId'], 'text' => $body['markdown']];
        }

        return $messages;
    }

    protected function answer(string $method, string $path, array $query, string $body, array $options): ResponseInterface
    {
        return match ($path) {
            '/v1/rooms' => $this->json(['items' => [
                ['id' => 'devs', 'title' => 'Devs'],
                ['id' => 'locked', 'title' => 'Locked'],
                ['id' => 'general', 'title' => 'General'],
            ]]),
            '/v1/memberships' => match ($query['roomId']) {
                'devs' => $this->json(['items' => $this->memberships(['W2', 'W3'])]),
                'general' => $this->json(['items' => $this->memberships(array_keys(self::PEOPLE))]),
                // A room the token cannot read is skipped by the application
                default => $this->json(['message' => 'Could not find a room with provided ID.'], 404),
            },
            '/v1/messages' => \in_array(json_decode($body, true)['toPersonId'], $this->failingUsers, true)
                ? $this->json(['message' => 'The requested resource could not be found.'], 404)
                : $this->json(['id' => 'M1', 'created' => '2026-12-01T10:00:00.000Z']),
            default => $this->json(['message' => 'Not found'], 404),
        };
    }

    /**
     * @param list<string> $personIds
     *
     * @return list<array<string, mixed>>
     */
    private function memberships(array $personIds): array
    {
        return array_map(static fn (string $id) => ['personId' => $id, 'personDisplayName' => self::PEOPLE[$id]], $personIds);
    }
}
