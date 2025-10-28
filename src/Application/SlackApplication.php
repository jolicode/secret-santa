<?php

/*
 * This file is part of the Secret Santa project.
 *
 * (c) JoliCode <coucou@jolicode.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace JoliCode\SecretSanta\Application;

use JoliCode\SecretSanta\Model\ApplicationToken;
use JoliCode\SecretSanta\Model\Config;
use JoliCode\SecretSanta\Model\SecretSanta;
use JoliCode\SecretSanta\Model\User;
use JoliCode\SecretSanta\Slack\MessageSender;
use JoliCode\SecretSanta\Slack\UserExtractor;
use Symfony\Component\Form\Extension\Core\Type\DateTimeType;
use Symfony\Component\Form\Extension\Core\Type\FormType;
use Symfony\Component\Form\Extension\Core\Type\HiddenType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\Session\SessionInterface;
use Symfony\Component\Validator\Constraints\GreaterThanOrEqual;
use Symfony\Component\Validator\Constraints\LessThanOrEqual;

class SlackApplication implements ApplicationInterface
{
    public const APPLICATION_CODE = 'slack';
    public const SESSION_KEY_STATE = 'santa.slack.state';

    private const SESSION_KEY_TOKEN = 'santa.slack.token';
    private const SESSION_KEY_ADMIN = 'santa.slack.admin';

    public function __construct(
        private RequestStack $requestStack,
        private UserExtractor $userExtractor,
        private MessageSender $messageSender,
    ) {
    }

    public function getCode(): string
    {
        return self::APPLICATION_CODE;
    }

    public function isAuthenticated(): bool
    {
        try {
            $this->getToken();

            return true;
        } catch (\LogicException $e) {
            return false;
        }
    }

    public function getAuthenticationRoute(): string
    {
        return 'slack_authenticate';
    }

    public function getOrganization(): string
    {
        return $this->getToken()->getContext()['team'] ?? '';
    }

    public function getAdmin(): ?User
    {
        return $this->getSession()->get(self::SESSION_KEY_ADMIN);
    }

    public function setAdmin(User $admin): void
    {
        $this->getSession()->set(self::SESSION_KEY_ADMIN, $admin);
    }

    public function getGroups(): array
    {
        return $this->userExtractor->extractGroups($this->getToken()->getToken());
    }

    public function loadNextBatchOfUsers(Config $config): array
    {
        return $this->userExtractor->loadNextBatchOfUsers($this->getToken()->getToken(), $config);
    }

    public function sendSecretMessage(SecretSanta $secretSanta, string $giver, string $receiver, bool $isSample = false): void
    {
        $this->messageSender->sendSecretMessage($secretSanta, $giver, $receiver, $this->getToken()->getToken(), $isSample);
    }

    public function sendAdminMessage(SecretSanta $secretSanta, string $code, string $spoilUrl): void
    {
        $this->messageSender->sendAdminMessage($secretSanta, $code, $spoilUrl, $this->getToken()->getToken());
    }

    public function configureMessageForm(FormBuilderInterface $builder): void
    {
        $builder->add(
            $builder->create('options', FormType::class)
                ->add('scheduled_at', HiddenType::class, [
                    'constraints' => [
                        new GreaterThanOrEqual([
                            'value' => (new \DateTime('+9 minutes'))->getTimestamp(),
                            'message' => 'You can only schedule a Secret Santa for at least 10 minutes away in the future',
                        ]),
                        new LessThanOrEqual([
                            'value' => (new \DateTime('+120 days'))->getTimestamp(),
                            'message' => 'You cannot schedule a Secret Santa for over 120 days in the future',
                        ]),
                    ],
                    'error_bubbling' => true,
                ])
                ->add('scheduled_at_tz', DateTimeType::class, [
                    'widget' => 'single_text',
                    'required' => false,
                    'error_bubbling' => true,
                ])
        );
    }

    public function reset(): void
    {
        $this->getSession()->remove(self::SESSION_KEY_TOKEN);
        $this->getSession()->remove(self::SESSION_KEY_ADMIN);
    }

    public function setToken(ApplicationToken $token): void
    {
        $this->getSession()->set(self::SESSION_KEY_TOKEN, $token);
    }

    private function getToken(): ApplicationToken
    {
        $token = $this->getSession()->get(self::SESSION_KEY_TOKEN);

        if (!$token instanceof ApplicationToken) {
            throw new \LogicException('Invalid token.');
        }

        return $token;
    }

    private function getSession(): SessionInterface
    {
        return $this->requestStack->getMainRequest()->getSession();
    }
}
