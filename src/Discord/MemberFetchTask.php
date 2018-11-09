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

use JoliCode\SecretSanta\Exception\UserExtractionFailedException;
use JoliCode\SecretSanta\Utils\LongTask;
use RestCord\Model\Guild\GuildMember;

class MemberFetchTask extends LongTask
{
    private $apiHelper;

    private $guildId;

    /** @var GuildMember[] */
    private $members = [];

    public function __construct(ApiHelper $apiHelper)
    {
        $this->apiHelper = $apiHelper;
    }

    public function setGuildId(string $guildId)
    {
        $this->guildId = $guildId;
    }

    protected function init()
    {
        if (!$this->guildId) {
            throw new \LogicException('Missing guild id to start the task.');
        }

        $this->members = [];
    }

    protected function getInitialValue()
    {
        return [];
    }

    protected function getResult()
    {
        return $this->members;
    }

    protected function iterate($value)
    {
        $lastMember = $value ? end($value) : null;

        try {
            /** @var GuildMember[] $lastMembers */
            $lastMembers = $this->apiHelper->getMembersInGuild($this->guildId, $lastMember ? $lastMember->user->id : null);
        } catch (\Throwable $t) {
            throw new UserExtractionFailedException('Could not fetch members in guild.', 0, $t);
        }

        $this->members = array_merge($this->members, $lastMembers);

        return $lastMembers;
    }

    protected function shouldContinue($value): bool
    {
        return !empty($value);
    }

    protected function onTimeOut()
    {
        throw new UserExtractionFailedException('Took too much time to retrieve all the users on your guild.');
    }
}
