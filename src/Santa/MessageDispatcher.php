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

use JoliCode\SecretSanta\Application\ApplicationInterface;
use JoliCode\SecretSanta\Exception\MessageDispatchTimeoutException;
use JoliCode\SecretSanta\Exception\MessageSendFailedException;
use JoliCode\SecretSanta\Model\SecretSanta;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

class MessageDispatcher
{
    /**
     * Send messages for remaining associations.
     *
     * This method is limited to 5 seconds to avoid being timed out by hosting.
     *
     * @throws MessageDispatchTimeoutException
     * @throws MessageSendFailedException
     */
    public function dispatchRemainingMessages(SecretSanta $secretSanta, ApplicationInterface $application): void
    {


        $startTime = time();

        foreach ($secretSanta->getRemainingAssociations() as $giver => $receiver) {
            if ((time() - $startTime) > 5) {
                throw new MessageDispatchTimeoutException($secretSanta);
            }

            $application->sendSecretMessage($secretSanta, $giver, $receiver);

            $secretSanta->markAssociationAsProceeded($giver);
        }

    }
}
