<?php

/*
 * This file is part of the Secret Santa project.
 *
 * (c) JoliCode <coucou@jolicode.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace JoliCode\SecretSanta\Discord;

use RestCord\DiscordClient;
use RestCord\Model\Guild\Guild;

class ApiHelper
{
    const TOKEN_TYPE_BOT = 'Bot';
    const TOKEN_TYPE_OAUTH = 'OAuth';

    private $botToken;

    public function __construct(string $discordBotToken)
    {
        $this->botToken = $discordBotToken;
    }

    public function getGuild(int $guildId): Guild
    {
        $client = $this->getClient($this->botToken, self::TOKEN_TYPE_BOT);

        return $client->guild->getGuild([
            'guild.id' => $guildId,
            'limit' => 100,
        ]);
    }

    public function getMembersInGuild(int $guildId): array
    {
        return $this->getClient($this->botToken, self::TOKEN_TYPE_BOT)->guild->listGuildMembers([
            'guild.id' => $guildId,
            'limit' => 100,
        ]);
    }

    public function getRolesInGuild(int $guildId): array
    {
        return $this->getClient($this->botToken, self::TOKEN_TYPE_BOT)->guild->getGuildRoles([
            'guild.id' => $guildId,
            'limit' => 100,
        ]);
    }

    public function sendMessage(int $userId, string $message): void
    {
        $client = $this->getClient($this->botToken, self::TOKEN_TYPE_BOT);

        $channel = $client->user->createDm([
            'recipient_id' => $userId,
        ]);

        $client->channel->createMessage([
            'channel.id' => $channel->id,
            'content' => $message,
        ]);
    }

    private function getClient(string $token, string $tokenType)
    {
        return new DiscordClient([
            'token' => $token,
            'tokenType' => $tokenType,
        ]);
    }
}
