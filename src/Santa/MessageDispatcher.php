<?php

/*
 * This file is part of the Secret Santa project.
 *
 * (c) JoliCode <coucou@jolicode.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace JoliCode\SecretSanta\Santa;

use Bugsnag\Client;
use JoliCode\SecretSanta\Application\ApplicationInterface;
use JoliCode\SecretSanta\Exception\MessageDispatchTimeoutException;
use JoliCode\SecretSanta\Exception\MessageSendFailedException;
use JoliCode\SecretSanta\Model\SecretSanta;
use Psr\Log\LoggerInterface;

class MessageDispatcher
{
    public function __construct(
        private LoggerInterface $logger,
        private Client $bugsnag,
    ) {
    }

    /**
     * Send messages for remaining associations.
     *
     * A failure does not stop the dispatch: the error is recorded on the giver and the other messages are still sent.
     *
     * This method is limited to 5 seconds to avoid being timed out by hosting.
     *
     * @throws MessageDispatchTimeoutException
     */
    public function dispatchRemainingMessages(SecretSanta $secretSanta, ApplicationInterface $application): void
    {
        $startTime = time();
        $failedUsers = $secretSanta->getErrors();

        foreach ($secretSanta->getRemainingAssociations() as $giver => $receiver) {
            if ((time() - $startTime) > 5) {
                throw new MessageDispatchTimeoutException($secretSanta);
            }

            if ($failedUsers[$giver] ?? false) {
                continue;
            }

            try {
                $application->sendSecretMessage($secretSanta, $giver, $receiver);
                $secretSanta->markAssociationAsProceeded($giver);
            } catch (MessageSendFailedException $e) {
                $secretSanta->addError($e->getMessage(), $giver);

                $this->logger->error($e->getMessage(), [
                    'exception' => $e,
                ]);
                $this->bugsnag->notifyException($e, static function ($report) {
                    $report->setSeverity('info');
                });
            }
        }
    }
}
