<?php

declare(strict_types=1);

namespace Nowo\HtmlToWordBundle\Tests\Unit\DependencyInjection;

use Nowo\HtmlToWordBundle\DependencyInjection\Configuration;
use Nowo\HtmlToWordBundle\DependencyInjection\HtmlToWordExtension;
use PHPUnit\Framework\TestCase;
use Symfony\Component\DependencyInjection\ContainerBuilder;

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
}
