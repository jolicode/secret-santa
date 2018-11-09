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
    private $spoiler;
    private $urlGenerator;
    private $longTaskManager;

    public function __construct(UrlGeneratorInterface $urlGenerator, Spoiler $spoiler)
    {
        $this->spoiler = $spoiler;
        $this->urlGenerator = $urlGenerator;
    }

    /**
     * Send messages for remaining associations.
     **
     * @throws MessageDispatchTimeoutException
     * @throws MessageSendFailedException
     */
    public function dispatchRemainingMessages(SecretSanta $secretSanta, ApplicationInterface $application): void
    {
        $this->longTaskManager->process(function () use ($secretSanta, $application) {
            $associations = $secretSanta->getRemainingAssociations();

            if (!$associations) {
                return;
            }

            reset($associations);
            $giver = key($associations);
            $receiver = current($associations);

            $application->sendSecretMessage($secretSanta, $giver, $receiver);

            $secretSanta->markAssociationAsProceeded($giver);
        }, function () use ($secretSanta) {
            return \count($secretSanta->getRemainingAssociations()) > 0;
        }, function () use ($secretSanta) {
            throw new MessageDispatchTimeoutException($secretSanta);
        });

        // Send a summary to the santa admin
        if ($secretSanta->getAdmin()) {
            $code = $this->spoiler->encode($secretSanta);
            $spoilUrl = $this->urlGenerator->generate('spoil', [], UrlGeneratorInterface::ABSOLUTE_URL);

            $application->sendAdminMessage($secretSanta, $code, $spoilUrl);
        }
    }
}
