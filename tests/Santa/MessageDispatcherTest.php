<?php

/*
 * This file is part of the Secret Santa project.
 *
 * (c) JoliCode <coucou@jolicode.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace JoliCode\SecretSanta\Tests\Santa;

use Bugsnag\Client;
use JoliCode\SecretSanta\Application\ApplicationInterface;
use JoliCode\SecretSanta\Exception\MessageSendFailedException;
use JoliCode\SecretSanta\Model\Config;
use JoliCode\SecretSanta\Model\SecretSanta;
use JoliCode\SecretSanta\Model\User;
use JoliCode\SecretSanta\Santa\MessageDispatcher;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;

class MessageDispatcherTest extends TestCase
{
    public function testAFailureDoesNotStopTheOtherMessages(): void
    {
        $config = new Config('slack', 'toto', null);
        $config->setAvailableUsers([
            'toto1' => new User('toto1', 'Toto 1'),
            'toto2' => new User('toto2', 'Toto 2'),
            'toto3' => new User('toto3', 'Toto 3'),
        ]);
        $secretSanta = new SecretSanta('hash', ['toto1' => 'toto2', 'toto2' => 'toto3', 'toto3' => 'toto1'], $config);

        $application = $this->createMock(ApplicationInterface::class);
        $application->method('sendSecretMessage')->willReturnCallback(function (SecretSanta $secretSanta, string $giver) {
            if ('toto1' === $giver) {
                throw new MessageSendFailedException($secretSanta, $secretSanta->getUser($giver));
            }
        });

        $dispatcher = new MessageDispatcher(new NullLogger(), $this->createMock(Client::class));
        $dispatcher->dispatchRemainingMessages($secretSanta, $application);

        self::assertSame(['toto1' => 'toto2'], $secretSanta->getRemainingAssociations());
        self::assertSame(['toto1'], array_keys($secretSanta->getErrors()));
        self::assertFalse($secretSanta->hasPendingAssociations());
        self::assertFalse($secretSanta->isDone());

        // Retry only hits the failed giver, which now succeeds
        $secretSanta->resetErrors();
        $sent = [];
        $application = $this->createMock(ApplicationInterface::class);
        $application->method('sendSecretMessage')->willReturnCallback(function (SecretSanta $secretSanta, string $giver) use (&$sent) {
            $sent[] = $giver;
        });
        $dispatcher->dispatchRemainingMessages($secretSanta, $application);

        self::assertSame(['toto1'], $sent);
        self::assertTrue($secretSanta->isDone());
    }
}
