<?php

/*
 * This file is part of the Slack Secret Santa project.
 *
 * (c) JoliCode <coucou@jolicode.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Joli\SlackSecretSanta\Controller;

use AdamPaterson\OAuth2\Client\Provider\Slack;
use CL\Slack\Payload\AuthTestPayload;
use CL\Slack\Transport\ApiClient;
use Joli\SlackSecretSanta\Rudolph;
use Joli\SlackSecretSanta\SecretDispatcher;
use Joli\SlackSecretSanta\SecretSanta;
use Joli\SlackSecretSanta\Spoiler;
use Joli\SlackSecretSanta\UserExtractor;
use League\OAuth2\Client\Token\AccessToken;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\Session\SessionInterface;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Routing\RouterInterface;

class ContentController extends AbstractController
{
    private $twig;

    public function __construct(\Twig_Environment $twig)
    {
        $this->twig = $twig;
    }

    public function homepage(): Response
    {
        $content = $this->twig->render('content/homepage.html.twig');

        return new Response($content);
    }
}
