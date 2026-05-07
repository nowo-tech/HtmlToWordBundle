<?php

declare(strict_types=1);

namespace Nowo\HtmlToWordBundle\Tests\Unit\Builder;

use Nowo\HtmlToWordBundle\Builder\ImageStyleHelper;
use PHPUnit\Framework\TestCase;

final class ImageStyleHelperTest extends TestCase
{
    public function testCssPxToPt(): void
    {
        self::assertSame(72.0, ImageStyleHelper::cssPxToPt(96.0));
        self::assertSame(36.0, ImageStyleHelper::cssPxToPt(48.0));
    }
}
