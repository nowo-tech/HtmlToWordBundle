<?php

declare(strict_types=1);

namespace Nowo\HtmlToWordBundle\DependencyInjection;

use Symfony\Component\Config\FileLocator;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Extension\Extension;
use Symfony\Component\DependencyInjection\Loader\YamlFileLoader;

/**
 * @author Héctor Franco Aceituno <hectorfranco@nowo.tech>
 */
final class HtmlToWordExtension extends Extension
{
    public function load(array $configs, ContainerBuilder $container): void
    {
        $loader = new YamlFileLoader($container, new FileLocator(__DIR__ . '/../Resources/config'));
        $loader->load('services.yaml');

        $config = $this->processConfiguration(new Configuration(), $configs);

        $container->setParameter(Configuration::ALIAS . '.engine', $config['engine']);
        $container->setParameter(Configuration::ALIAS . '.default_profile', $config['default_profile']);
        $container->setParameter(Configuration::ALIAS . '.profiles', $config['profiles']);
    }

    public function getAlias(): string
    {
        return Configuration::ALIAS;
    }
}
