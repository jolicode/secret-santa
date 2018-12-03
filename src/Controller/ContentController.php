<?php

/*
 * This file is part of the Secret Santa project.
 *
 * (c) JoliCode <coucou@jolicode.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace JoliCode\SecretSanta\Controller;

use JoliCode\SecretSanta\Statistic\StatisticCollector;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;

class ContentController extends AbstractController
{
    private $twig;
    private $statisticCollector;

    public function __construct(\Twig_Environment $twig, StatisticCollector $statisticCollector)
    {
        $this->twig = $twig;
        $this->statisticCollector = $statisticCollector;
    }

    public function homepage(): Response
    {
        $content = $this->twig->render('content/homepage.html.twig');

        return new Response($content);
    }

    public function terms(): Response
    {
        $content = $this->twig->render('content/terms.html.twig');

        return new Response($content);
    }

    public function privacyPolicy(): Response
    {
        $content = $this->twig->render('content/privacy_policy.html.twig');

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
                'label' => 'ACSEO',
                'link' => 'https://www.acseo.fr',
                'image' => 'acseo.jpg',
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
                'image' => 'digital-ping-pong.svg',
            ],
            [
                'label' => 'EvaluAgent',
                'link' => 'https://www.evaluagent.com/',
                'image' => 'evaluagent.png',
            ],
            [
                'label' => 'Fabernovel Institute',
                'link' => 'http://institute.fabernovel.com',
                'image' => 'fabernovel-institute.png',
            ],
            [
                'label' => 'Ferdio',
                'link' => 'https://ferdio.com',
                'image' => 'ferdio.svg',
            ],
            [
                'label' => 'IdeaFoster',
                'link' => 'https://www.ideafoster.com',
                'image' => 'ideafoster.png',
            ],
            [
                'label' => 'Idra',
                'link' => 'https://www.idracompany.com/',
                'image' => 'IDRA.png',
            ],
            [
                'label' => 'JoliCode',
                'link' => 'https://jolicode.com/',
                'image' => 'jolicode.svg',
            ],
            [
                'label' => 'KvikyMart',
                'link' => 'https://kvikymart.com',
                'image' => 'kvikymart.svg',
            ],
            [
                'label' => 'Les-Tilleuls.coop',
                'link' => 'https://les-tilleuls.coop/',
                'image' => 'les-tilleuls.png',
            ],
            [
                'label' => 'Living Actor',
                'link' => 'https://www.livingactor.com',
                'image' => 'livingactor.png',
            ],
            [
                'label' => 'M6web',
                'link' => 'https://tech.m6web.fr/',
                'image' => 'm6web.png',
            ],
            [
                'label' => 'Melvin & Hamilton',
                'link' => 'https:///www.melvin-hamilton.com',
                'image' => 'melvin-hamilton.png',
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
                'label' => 'Redirection.io',
                'link' => 'https://redirection.io/',
                'image' => 'redirectionio.svg',
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

    public function stats(): Response
    {
        $content = $this->twig->render('content/stats.html.twig', [
            'counters' => $this->statisticCollector->getCounters(),
        ]);

        return new Response($content);
    }
}
