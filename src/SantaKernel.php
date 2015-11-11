<?php

namespace Joli\SlackSecretSanta;

use Symfony\Bundle\FrameworkBundle\FrameworkBundle;
use Symfony\Bundle\FrameworkBundle\Kernel\MicroKernelTrait;
use Symfony\Bundle\TwigBundle\TwigBundle;
use Symfony\Component\Config\Loader\LoaderInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Parameter;
use Symfony\Component\DependencyInjection\Reference;
use Symfony\Component\HttpKernel\Kernel;
use Symfony\Component\Routing\RouteCollectionBuilder;

class SantaKernel extends Kernel
{
    use MicroKernelTrait;

    public function registerBundles()
    {
        $bundles = [
            new FrameworkBundle(),
            new TwigBundle(),
        ];

        return $bundles;
    }

    /**
     * Add or import routes into your application.
     *
     *     $routes->import('config/routing.yml');
     *     $routes->add('/admin', 'AppBundle:Admin:dashboard', 'admin_dashboard');
     *
     * @param RouteCollectionBuilder $routes
     */
    protected function configureRoutes(RouteCollectionBuilder $routes)
    {
        $routes->add('/', 'santa.controller:homepage', 'homepage');
        $routes->add('/auth', 'santa.controller:authenticate', 'authenticate');
        $routes->add('/command', 'santa.controller:command', 'command');
    }

    /**
     * Configures the container.
     *
     * You can register extensions:
     *
     * $c->loadFromExtension('framework', array(
     *     'secret' => '%secret%'
     * ));
     *
     * Or services:
     *
     * $c->register('halloween', 'FooBundle\HalloweenProvider');
     *
     * Or parameters:
     *
     * $c->setParameter('halloween', 'lot of fun');
     *
     * @param ContainerBuilder $c
     * @param LoaderInterface  $loader
     */
    protected function configureContainer(ContainerBuilder $c, LoaderInterface $loader)
    {
        $c->loadFromExtension('framework', [
          'secret'  => 'NotSoRandom...:)',
          'session' => true,
        ]);
        $c->loadFromExtension('twig', [
          'paths'  => [
              __DIR__.'/../views/'
          ]
        ]);

        if (empty($_ENV['SLACK_CLIENT_SECRET']) || empty($_ENV['SLACK_CLIENT_ID'])) {
            $_ENV['SLACK_CLIENT_SECRET'] = 'dummy';
            $_ENV['SLACK_CLIENT_ID'] = 'dummy';
        }

        // Slack application credentials
        $c->setParameter('slack.client_secret', $_ENV['SLACK_CLIENT_SECRET']);
        $c->setParameter('slack.client_id', $_ENV['SLACK_CLIENT_ID']);

        $controller = $c->register('santa.controller', 'Joli\SlackSecretSanta\Controller\SantaController');
        $controller->addArgument(new Reference('session'));
        $controller->addArgument(new Reference('router'));
        $controller->addArgument(new Reference('twig'));
        $controller->addArgument(new Parameter('slack.client_id'));
        $controller->addArgument(new Parameter('slack.client_secret'));
/**
        $twig = $c->getDefinition('twig.loader');

        $loader = new \Twig_Loader_Filesystem(array());
        $loader->addPath(__DIR__.'/../views/');
        $twig->addMethodCall('addLoader', $loader);
**/
    }

    /**
     * {@inheritdoc}
     */
    public function getCacheDir()
    {
        return $this->rootDir . '/../var/cache/' . $this->environment;
    }

    /**
     * {@inheritdoc}
     */
    public function getLogDir()
    {
        return $this->rootDir . '/../var/logs';
    }
}
