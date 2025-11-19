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
    public function __construct(
        private Environment $twig,
        private StatisticCollector $statisticCollector,
    ) {
    }

    #[Route('/', name: 'homepage', methods: ['GET'])]
    public function homepage(): Response
    {
        $content = $this->twig->render('content/homepage.html.twig');

        return new Response($content);
    }

    #[Route('/terms-of-service', name: 'terms', methods: ['GET'])]
    public function terms(): Response
    {
        $content = $this->twig->render('content/terms.html.twig');

        return new Response($content);
    }

    #[Route('/privacy-policy', name: 'privacy_policy', methods: ['GET'])]
    public function privacyPolicy(): Response
    {
        $content = $this->twig->render('content/privacy_policy.html.twig');

        return new Response($content);
    }

    #[Route('/hall-of-fame', name: 'hall_of_fame', methods: ['GET'])]
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
                'label' => 'Arbuckle Media',
                'link' => 'https://arbuckle.media',
                'image' => 'arbuckle-media.png',
            ],
            [
                'label' => 'Beyonds',
                'link' => 'https://www.beyonds.fr/',
                'image' => 'beyonds.svg',
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
                'label' => 'Digital Darts',
                'link' => 'https://www.digitaldarts.com.au',
                'image' => 'digital-darts.png',
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
                'link' => 'http://www.fabernovel.com',
                'image' => 'fabernovel-institute.png',
            ],
            [
                'label' => 'Ferdio',
                'link' => 'https://www.ferdio.com',
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
                'link' => 'https://ideafoster.com',
                'image' => 'ideafoster.png',
            ],
            [
                'label' => 'Idra',
                'link' => 'https://www.idracompany.com/',
                'image' => 'IDRA.png',
            ],
            [
                'label' => 'Intent',
                'link' => 'https://www.withintent.com/',
                'image' => 'intent.png',
            ],
            [
                'label' => 'IS Decisions',
                'link' => 'https://www.isdecisions.com/',
                'image' => 'isd.jpg',
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
                'label' => 'Leroy Automation',
                'link' => 'https://www.leroy-automation.com/',
                'image' => 'leroy.svg',
            ],
            [
                'label' => 'Les-Tilleuls.coop',
                'link' => 'https://les-tilleuls.coop/',
                'image' => 'les-tilleuls.png',
            ],
            [
                'label' => 'Lionix',
                'link' => 'https://lionix.io/',
                'image' => 'lionix.svg',
            ],
            [
                'label' => 'Living Actor',
                'link' => 'https://www.livingactor.com',
                'image' => 'livingactor.png',
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
                'link' => 'https://www.perkbox.com/',
                'image' => 'perkbox-logo.png',
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
                'label' => 'Rome Blockchain Labs',
                'link' => 'https://romeblockchain.com/',
                'image' => 'romeblockchain.png',
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
                'label' => 'Stream.tv',
                'link' => 'https://stream.tv/',
                'image' => 'streamtv.png',
            ],
            [
                'label' => 'TMRR',
                'link' => 'https://temeraire-marketing.lu/',
                'image' => 'tmrr.png',
            ],
            [
                'label' => 'Uppler',
                'link' => 'https://uppler.com/',
                'image' => 'uppler.png',
            ],
            [
                'label' => 'Yoco Co',
                'link' => 'https://www.yokoco.com/',
                'image' => 'yococo.jpg',
            ],
        ];

        $content = $this->twig->render('content/hall_of_fame.html.twig', [
            'companies' => $companies,
        ]);

        return new Response($content);
    }

    #[Route('/stats', name: 'stats', methods: ['GET'])]
    public function stats(): Response
    {
        $content = $this->twig->render('content/stats.html.twig', [
            'counters' => $this->statisticCollector->getCounters(),
        ]);

        return new Response($content);
    }
}
