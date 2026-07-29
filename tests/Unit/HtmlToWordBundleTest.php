<?php

declare(strict_types=1);

namespace Nowo\HtmlToWordBundle\Tests\Unit;

use Nowo\HtmlToWordBundle\DependencyInjection\HtmlToWordExtension;
use Nowo\HtmlToWordBundle\HtmlToWordBundle;
use PHPUnit\Framework\TestCase;

final class HtmlToWordBundleTest extends TestCase
{
    public function testGetContainerExtensionReturnsSingleton(): void
    {
        $bundle = new HtmlToWordBundle();
        $ext1   = $bundle->getContainerExtension();
        $ext2   = $bundle->getContainerExtension();

        self::assertInstanceOf(HtmlToWordExtension::class, $ext1);
        self::assertSame($ext1, $ext2);
    }
}
