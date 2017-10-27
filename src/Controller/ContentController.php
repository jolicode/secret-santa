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
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;

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

    public function hallOfFame(): Response
    {
        $companies = [
            [
                'label' => '20minutes',
                'link' => 'http://www.20minutes.fr/',
                'image' => '20minutes.png',
            ],
            [
                'label' => 'BlaBlaCar',
                'link' => 'https://www.blablacar.fr/',
                'image' => 'blablacar.png',
            ],
            [
                'label' => 'Cap Collectif',
                'link' => 'https://cap-collectif.com/',
                'image' => 'cap-collectif.png',
            ],
            [
                'label' => 'Digital Ping Pong',
                'link' => 'https://digitalpingpong.com/',
                'image' => 'digital-ping-pong.jpg',
            ],
            [
                'label' => 'JoliCode',
                'link' => 'https://jolicode.com/',
                'image' => 'jolicode.svg',
            ],
            [
                'label' => 'Les-Tilleuls.coop',
                'link' => 'https://les-tilleuls.coop/',
                'image' => 'les-tilleuls.png',
            ],
            [
                'label' => 'Monsieur Biz',
                'link' => 'https://monsieurbiz.com/',
                'image' => 'monsieur-biz.png',
            ],
            [
                'label' => 'Perkbox',
                'link' => 'https://www.perkbox.co.uk/',
                'image' => 'perkbox-logo.png',
            ],
            [
                'label' => 'SensioLabs',
                'link' => 'https://sensiolabs.com/',
                'image' => 'sensiolabs.png',
            ],
        ];

        $content = $this->twig->render('content/hall_of_fame.html.twig', [
            'companies' => $companies,
        ]);

        return new Response($content);
    }
}
