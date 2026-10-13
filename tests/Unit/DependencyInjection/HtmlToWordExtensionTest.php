<?php

declare(strict_types=1);

namespace Nowo\HtmlToWordBundle\Tests\Unit\DependencyInjection;

use Nowo\HtmlToWordBundle\DependencyInjection\Configuration;
use Nowo\HtmlToWordBundle\DependencyInjection\HtmlToWordExtension;
use Nowo\HtmlToWordBundle\Export\DocxExporter;
use PHPUnit\Framework\TestCase;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Reference;
use Symfony\Component\DependencyInjection\ServiceLocator;

final class HtmlToWordExtensionTest extends TestCase
{
    public function testLoadSetsParametersAndAlias(): void
    {
        $extension = new HtmlToWordExtension();
        $container = new ContainerBuilder();

        $extension->load([
            [
                'engine'          => 'phpword',
                'default_profile' => 'default',
                'profiles'        => [
                    'default' => ['strict_mode' => false],
                ],
            ],
        ], $container);

        self::assertSame(Configuration::ALIAS, $extension->getAlias());
        self::assertSame('phpword', $container->getParameter(Configuration::ALIAS . '.engine'));
        self::assertSame('default', $container->getParameter(Configuration::ALIAS . '.default_profile'));
        self::assertIsArray($container->getParameter(Configuration::ALIAS . '.profiles'));
    }

    public function testNoFlysystemAdapterKeepsExporterLocatorNull(): void
    {
        $container = new ContainerBuilder();
        (new HtmlToWordExtension())->load([['profiles' => ['default' => []]]], $container);

        self::assertNull($container->getDefinition(DocxExporter::class)->getArgument('$flysystemAdapters'));
    }

    public function testProfileFlysystemAdaptersAreExposedThroughLocator(): void
    {
        $container = new ContainerBuilder();
        (new HtmlToWordExtension())->load([[
            'profiles' => [
                'default' => ['export' => ['flysystem_adapter' => 'app.default_storage']],
                'archive' => ['export' => ['flysystem_adapter' => ' app.archive_storage ']],
                'blank'   => ['export' => ['flysystem_adapter' => '  ']],
            ],
        ]], $container);

        $locatorRef = $container->getDefinition(DocxExporter::class)->getArgument('$flysystemAdapters');
        self::assertInstanceOf(Reference::class, $locatorRef);

        $locator = $container->getDefinition((string) $locatorRef);
        self::assertSame(ServiceLocator::class, $locator->getClass());
        $factories = $locator->getArgument(0);
        self::assertIsArray($factories);
        self::assertSame(['app.archive_storage', 'app.default_storage'], array_keys($factories));
    }
}
