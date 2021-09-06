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
use Symfony\Component\Routing\Annotation\Route;
use Twig\Environment;

class ContentController extends AbstractController
{
    private $twig;
    private $statisticCollector;

    public function __construct(Environment $twig, StatisticCollector $statisticCollector)
    {
        $this->twig = $twig;
        $this->statisticCollector = $statisticCollector;
    }

    #[Route('/', name:'homepage', methods:['GET'])]
    public function homepage(): Response
    {
        $content = $this->twig->render('content/homepage.html.twig');

        return new Response($content);
    }

    #[Route('/terms-of-service', name:'terms', methods:['GET'])]
    public function terms(): Response
    {
        $content = $this->twig->render('content/terms.html.twig');

        return new Response($content);
    }

    #[Route('/privacy-policy', name:'privacy_policy', methods:['GET'])]
    public function privacyPolicy(): Response
    {
        $content = $this->twig->render('content/privacy_policy.html.twig');

        return new Response($content);
    }

    #[Route('/hall-of-fame', name:'hall_of_fame', methods:['GET'])]
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
                'label' => 'ApparelMagic',
                'link' => 'https://www.apparelmagic.com/',
                'image' => 'apparelmagic.png',
            ],
            [
                'label' => 'BlaBlaCar',
                'link' => 'https://www.blablacar.fr/',
                'image' => 'blablacar.png',
            ],
            [
                'label' => 'Buy Me a Coffee',
                'link' => 'https://www.buymeacoffee.com/',
                'image' => 'buymeacoffee.png',
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
                'label' => 'Elao',
                'link' => 'https://www.elao.com',
                'image' => 'elao.jpg',
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
                'label' => 'Get A Copywriter',
                'link' => 'https://getacopywriter.com',
                'image' => 'getacopywriter.png',
            ],
            [
                'label' => 'Happy Team',
                'link' => 'https://happyteam.io',
                'image' => 'happyteam.png',
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
                'label' => 'Ingot Portal',
                'link' => 'https://ingotportal.com',
                'image' => 'ingot.jpg',
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
                'label' => 'Makers',
                'link' => 'https://makers.tech/',
                'image' => 'makers.png',
            ],
            [
                'label' => 'Mixd',
                'link' => 'https://www.mixd.co.uk/',
                'image' => 'mixd.png',
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
                'label' => 'PipeCandy',
                'link' => 'https://pipecandy.com/',
                'image' => 'pipecandy.png',
            ],
            [
                'label' => 'Postman',
                'link' => 'https://www.postman.com/',
                'image' => 'postman.svg',
            ],
            [
                'label' => 'Redirection.io',
                'link' => 'https://redirection.io/',
                'image' => 'redirectionio.svg',
            ],
            [
                'label' => 'Sencrop',
                'link' => 'https://sencrop.com/',
                'image' => 'sencrop.png',
            ],

            [
                'label' => 'SensioLabs',
                'link' => 'https://sensiolabs.com/',
                'image' => 'sensiolabs.png',
            ],
            [
                'label' => 'Uppler',
                'link' => 'https://uppler.com/',
                'image' => 'uppler.png',
            ],
        ];

        $content = $this->twig->render('content/hall_of_fame.html.twig', [
            'companies' => $companies,
        ]);

        return new Response($content);
    }

    #[Route('/stats', name:'stats', methods:['GET'])]
    public function stats(): Response
    {
        $content = $this->twig->render('content/stats.html.twig', [
            'counters' => $this->statisticCollector->getCounters(),
        ]);

        return new Response($content);
    }
}
