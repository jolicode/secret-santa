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
use RestCord\Model\Guild\GuildMember;
use RestCord\Model\Permissions\Role;

class ApiHelper
{
    const TOKEN_TYPE_BOT = 'Bot';
    private $botToken;
    
    /** @var DiscordClient|null */
    private $client;

    public function __construct(string $discordBotToken)
    {
        $this->botToken = $discordBotToken;
    }

    public function getGuild(int $guildId): Guild
    {
        $client = $this->getClient();

        return $client->guild->getGuild([
            'guild.id' => $guildId,
        ]);
    }

    /**
     * @return GuildMember[]
     */
    public function getMembersInGuild(int $guildId, int $after = null): array
    {
        return $this->getClient()->guild->listGuildMembers([
            'guild.id' => $guildId,
            'limit' => 200,
            'after' => $after,
        ]);
    }

    /**
     * @return Role[]
     */
    public function getRolesInGuild(int $guildId): array
    {
        return $this->getClient()->guild->getGuildRoles([
            'guild.id' => $guildId,
            'limit' => 100,
        ]);
    }

    public function sendMessage(int $userId, string $message): void
    {
        $client = $this->getClient();

        $channel = $client->user->createDm([
            'recipient_id' => $userId,
        ]);

        $client->channel->createMessage([
            'channel.id' => $channel->id,
            'content' => $message,
        ]);
    }

    private function getClient(): DiscordClient
    {
        if (!($this->client instanceof DiscordClient)) {
            $this->client = new DiscordClient([
                'token' => $this->botToken,
                'tokenType' => self::TOKEN_TYPE_BOT,
            ]);
        }

        return $this->client;
    }
}
