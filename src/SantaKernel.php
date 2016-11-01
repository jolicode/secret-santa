<?php

namespace Joli\SlackSecretSanta;

use Joli\SlackSecretSanta\Controller\SantaController;
use Predis\Client;
use Predis\Session\Handler;
use Symfony\Bundle\DebugBundle\DebugBundle;
use Symfony\Bundle\FrameworkBundle\FrameworkBundle;
use Symfony\Bundle\FrameworkBundle\Kernel\MicroKernelTrait;
use Symfony\Bundle\TwigBundle\TwigBundle;
use Symfony\Bundle\WebProfilerBundle\WebProfilerBundle;
use Symfony\Component\Config\Loader\LoaderInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Parameter;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Kernel;
use Symfony\Component\Routing\RouteCollectionBuilder;

class SantaKernel extends Kernel
{
    use MicroKernelTrait;

    public function __construct($environment, $debug)
    {
        Request::setTrustedProxies(['0.0.0.0/0']);

        parent::__construct($environment, $debug);
    }

    public function registerBundles()
    {
        $bundles = [
            new FrameworkBundle(),
            new TwigBundle(),
        ];

        if ($this->getEnvironment() === 'dev') {
            $bundles[] = new DebugBundle();
            $bundles[] = new WebProfilerBundle();
        }

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
        if (isset($_ENV['FORCE_SSL'])) {
            $routes->setSchemes('https');
        }

        if ($this->getEnvironment() === 'dev') {
            $routes->import('@WebProfilerBundle/Resources/config/routing/wdt.xml', '/_wdt');
            $routes->import('@WebProfilerBundle/Resources/config/routing/profiler.xml', '/_profiler');
            $routes->import('@TwigBundle/Resources/config/routing/errors.xml', '/_error');
        }

        $routes->add('/', 'santa.controller:homepage', 'homepage');
        $routes->add('/run', 'santa.controller:run', 'run');
        $routes->add('/finish/{hash}', 'santa.controller:finish', 'finish');
        $routes->add('/auth', 'santa.controller:authenticate', 'authenticate');
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
        $session = [
            'handler_id' => 'session.handler.predis',
            'name' => 'santaSession',
        ];

        if ($c->getParameter('kernel.environment') === 'test') {
            $session['storage_id'] = 'session.storage.filesystem';
            $session['handler_id'] = 'session.handler.native_file';
        }

        $c->loadFromExtension('framework', [
          'secret' => 'NotSoRandom...:)',
          'session' => $session,
          'assets' => [],
          'templating' => [
              'engines' => ['twig'],
          ],
        ]);
        $c->loadFromExtension('twig', [
          'debug' => '%kernel.debug%',
        ]);

        if ($c->getParameter('kernel.environment') === 'dev') {
            $c->loadFromExtension('framework', [
                'profiler' => [
                    'only_exceptions' => false,
                ],
            ]);
            $c->loadFromExtension('web_profiler', [
                'toolbar' => true,
                'intercept_redirects' => false,
            ]);
        }

        if (empty($_ENV['SLACK_CLIENT_SECRET']) || empty($_ENV['SLACK_CLIENT_ID'])) {
            $_ENV['SLACK_CLIENT_SECRET'] = 'dummy';
            $_ENV['SLACK_CLIENT_ID'] = 'dummy';
        }

        if (empty($_ENV['REDIS_URL'])) {
            $_ENV['REDIS_URL'] = 'redis://localhost:6379';
        }

        // Slack application credentials
        $c->setParameter('slack.client_secret', $_ENV['SLACK_CLIENT_SECRET']);
        $c->setParameter('slack.client_id', $_ENV['SLACK_CLIENT_ID']);

        $controller = $c->register('santa.controller', SantaController::class);
        $controller->setAutowired(true);
        $controller->addArgument(new Parameter('slack.client_id'));
        $controller->addArgument(new Parameter('slack.client_secret'));

        $sessionHandler = $c->register('session.handler.predis', Handler::class);
        $sessionHandler->setPublic(false);
        $sessionHandler->setAutowired(true);

        $predis = $c->register('predis', Client::class);
        $predis->setPublic(false);
        $predis->addArgument($_ENV['REDIS_URL']);
    }

    /**
     * {@inheritdoc}
     */
    public function getRootDir()
    {
        if (null === $this->rootDir) {
            $this->rootDir = dirname(__DIR__);
        }

        return $this->rootDir;
    }

    /**
     * {@inheritdoc}
     */
    public function getCacheDir()
    {
        return $this->rootDir . '/var/cache/' . $this->environment;
    }

    /**
     * {@inheritdoc}
     */
    public function getLogDir()
    {
        return $this->rootDir . '/var/logs';
    }
}
