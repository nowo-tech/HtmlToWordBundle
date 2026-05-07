<?php

declare(strict_types=1);

namespace Nowo\HtmlToWordBundle\Tests\Fixtures;

use Nowo\HtmlToWordBundle\HtmlToWordBundle;
use Symfony\Bundle\FrameworkBundle\FrameworkBundle;
use Symfony\Bundle\FrameworkBundle\Kernel\MicroKernelTrait;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;
use Symfony\Component\HttpKernel\Kernel;
use Symfony\Component\Routing\Loader\Configurator\RoutingConfigurator;

/**
 * Minimal Symfony kernel for integration tests (loads HtmlToWordBundle + YAML fixtures).
 */
class AppKernel extends Kernel
{
    use MicroKernelTrait;

    public function __construct()
    {
        parent::__construct('test', false);
    }

    public function registerBundles(): iterable
    {
        yield new FrameworkBundle();
        yield new HtmlToWordBundle();
    }

    protected function configureContainer(ContainerConfigurator $container): void
    {
        $container->import(__DIR__ . '/config/packages/*.yaml');
    }

    protected function configureRoutes(RoutingConfigurator $routes): void
    {
    }

    public function getCacheDir(): string
    {
        return sys_get_temp_dir() . '/html_to_word_bundle_tests/cache/' . spl_object_hash($this);
    }

    public function getLogDir(): string
    {
        return sys_get_temp_dir() . '/html_to_word_bundle_tests/log/' . spl_object_hash($this);
    }
}
