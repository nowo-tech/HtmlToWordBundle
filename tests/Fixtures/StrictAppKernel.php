<?php

declare(strict_types=1);

namespace Nowo\HtmlToWordBundle\Tests\Fixtures;

use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;

/**
 * Same as {@see AppKernel} but loads strict-mode YAML fixtures.
 */
final class StrictAppKernel extends AppKernel
{
    protected function configureContainer(ContainerConfigurator $container): void
    {
        $container->import(__DIR__ . '/config_strict/packages/*.yaml');
    }
}
