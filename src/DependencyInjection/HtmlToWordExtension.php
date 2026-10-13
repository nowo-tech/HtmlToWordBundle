<?php

declare(strict_types=1);

namespace Nowo\HtmlToWordBundle\DependencyInjection;

use Nowo\HtmlToWordBundle\Export\DocxExporter;
use Symfony\Component\Config\FileLocator;
use Symfony\Component\DependencyInjection\Compiler\ServiceLocatorTagPass;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Extension\Extension;
use Symfony\Component\DependencyInjection\Loader\YamlFileLoader;
use Symfony\Component\DependencyInjection\Reference;

use function is_string;
use function ksort;
use function trim;

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

        $this->registerFlysystemAdapters($container, $config['profiles']);
    }

    /**
     * Exposes every {@code profiles.*.export.flysystem_adapter} service id to {@see DocxExporter} through a
     * service locator (only ids declared in YAML are reachable; a missing service fails at container compile).
     *
     * @param array<string, array<string, mixed>> $profiles
     */
    private function registerFlysystemAdapters(ContainerBuilder $container, array $profiles): void
    {
        $refs = [];
        foreach ($profiles as $profile) {
            $id = $profile['export']['flysystem_adapter'] ?? null;
            if (is_string($id) && trim($id) !== '') {
                $id        = trim($id);
                $refs[$id] = new Reference($id);
            }
        }

        if ($refs === []) {
            return;
        }

        ksort($refs);
        $container->getDefinition(DocxExporter::class)
            ->setArgument('$flysystemAdapters', ServiceLocatorTagPass::register($container, $refs));
    }

    public function getAlias(): string
    {
        return Configuration::ALIAS;
    }
}
