<?php
namespace Joli\SlackSecretSanta;

use Symfony\Bundle\FrameworkBundle\FrameworkBundle;
use Symfony\Bundle\FrameworkBundle\Kernel\MicroKernelTrait;
use Symfony\Bundle\TwigBundle\TwigBundle;
use Symfony\Component\Config\Loader\LoaderInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\HttpFoundation\Response;
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

    public function home()
    {
        return new Response('Coucou, change me!');
    }

    /**
     * Loads the container configuration.
     *
     * @param LoaderInterface $loader A LoaderInterface instance
     */
    public function registerContainerConfiguration(LoaderInterface $loader)
    {
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
     * @param LoaderInterface $loader
     */
    protected function configureContainer(ContainerBuilder $c, LoaderInterface $loader)
    {
    }
}
