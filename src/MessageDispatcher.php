<?php

/*
 * This file is part of the Slack Secret Santa project.
 *
 * (c) JoliCode <coucou@jolicode.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Joli\SlackSecretSanta;

use Joli\SlackSecretSanta\Application\ApplicationInterface;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

class MessageDispatcher
{
    /** @var Spoiler */
    private $spoiler;

    /** @var UrlGeneratorInterface */
    private $urlGenerator;

    public function __construct(UrlGeneratorInterface $urlGenerator, Spoiler $spoiler)
    {
        $this->spoiler = $spoiler;
        $this->urlGenerator = $urlGenerator;
    }

    /**
     * Send messages for remaining associations.
     *
     * This method is limited to 20 seconds to be able to display nice error message instead of being timed out by Heroku.
     *
     * @throws \RuntimeException
     */
    public function dispatchRemainingMessages(SecretSanta $secretSanta, ApplicationInterface $application): void
    {
        $startTime = time();

        try {
            foreach ($secretSanta->getRemainingAssociations() as $giver => $receiver) {
                if ((time() - $startTime) > 19) {
                    throw new \RuntimeException('It takes too much time to send messages!');
                }

                $application->sendSecretMessage($secretSanta, $giver, $receiver);

                $secretSanta->markAssociationAsProceeded($giver);
            }

            // Send a summary to the santa admin
            if ($secretSanta->getAdmin()) {
                $code = $this->spoiler->encode($secretSanta);
                $spoilUrl = $this->urlGenerator->generate('spoil', [], UrlGeneratorInterface::ABSOLUTE_URL);

                $application->sendAdminMessage($secretSanta, $code, $spoilUrl);
            }
        } catch (\Throwable $t) {
            $secretSanta->addError($t->getMessage());
        }
    }
}
