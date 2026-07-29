<?php

declare(strict_types=1);

namespace Nowo\HtmlToWordBundle\Tests\Unit\Builder;

use Nowo\HtmlToWordBundle\Builder\ImageStyleHelper;
use PHPUnit\Framework\TestCase;

final class ImageStyleHelperTest extends TestCase
{
    private const PNG_1X1 = 'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mP8z8BQDwAEhQGAhKmMIQAAAABJRU5ErkJggg==';

    public function testCssPxToPt(): void
    {
        self::assertSame(72.0, ImageStyleHelper::cssPxToPt(96.0));
        self::assertSame(36.0, ImageStyleHelper::cssPxToPt(48.0));
    }

    public function testCompleteEmbeddingStyleWhenGetimagesizeFailsUsesHtmlAttrs(): void
    {
        $tmp = tempnam(sys_get_temp_dir(), 'htw_style_bad_');
        self::assertNotFalse($tmp);
        try {
            file_put_contents($tmp, 'not-an-image');
            $style = ImageStyleHelper::completeEmbeddingStyle($tmp, ['width' => 300, 'height' => 150], 200.0);
            self::assertSame(ImageStyleHelper::cssPxToPt(200.0), $style['width']);
            self::assertSame(ImageStyleHelper::cssPxToPt(150.0), $style['height']);
            self::assertSame('inline', $style['wrappingStyle']);
        } finally {
            @unlink($tmp);
        }
    }

    public function testCompleteEmbeddingStyleWhenGetimagesizeFailsUsesDefaults(): void
    {
        $tmp = tempnam(sys_get_temp_dir(), 'htw_style_bad2_');
        self::assertNotFalse($tmp);
        try {
            file_put_contents($tmp, 'x');
            $style = ImageStyleHelper::completeEmbeddingStyle($tmp, [], 500.0);
            self::assertSame(ImageStyleHelper::cssPxToPt(200.0), $style['width']);
            self::assertSame(ImageStyleHelper::cssPxToPt(120.0), $style['height']);
        } finally {
            @unlink($tmp);
        }
    }

    public function testCompleteEmbeddingStyleWidthOnlyUsesAspectRatio(): void
    {
        $path = $this->createTempPng();
        try {
            $style = ImageStyleHelper::completeEmbeddingStyle($path, ['width' => 100], 600.0);
            self::assertSame(ImageStyleHelper::cssPxToPt(100.0), $style['width']);
            self::assertSame(ImageStyleHelper::cssPxToPt(100.0), $style['height']);
        } finally {
            @unlink($path);
        }
    }

    public function testCompleteEmbeddingStyleHeightOnlyUsesAspectRatio(): void
    {
        $path = $this->createTempPng();
        try {
            $style = ImageStyleHelper::completeEmbeddingStyle($path, ['height' => 80], 600.0);
            self::assertSame(ImageStyleHelper::cssPxToPt(80.0), $style['height']);
            self::assertSame(ImageStyleHelper::cssPxToPt(80.0), $style['width']);
        } finally {
            @unlink($path);
        }
    }

    public function testCompleteEmbeddingStyleWithoutAttrsUsesSourceSize(): void
    {
        $path = $this->createTempPng();
        try {
            $style = ImageStyleHelper::completeEmbeddingStyle($path, [], 600.0);
            self::assertSame(ImageStyleHelper::cssPxToPt(1.0), $style['width']);
            self::assertSame(ImageStyleHelper::cssPxToPt(1.0), $style['height']);
        } finally {
            @unlink($path);
        }
    }

    public function testHeaderLogoStyleWhenGetimagesizeFailsUsesSquare(): void
    {
        $tmp = tempnam(sys_get_temp_dir(), 'htw_logo_bad_');
        self::assertNotFalse($tmp);
        try {
            file_put_contents($tmp, 'nope');
            $style = ImageStyleHelper::headerLogoStyle($tmp, 48);
            $pt    = ImageStyleHelper::cssPxToPt(48.0);
            self::assertSame($pt, $style['width']);
            self::assertSame($pt, $style['height']);
        } finally {
            @unlink($tmp);
        }
    }

    public function testHeaderLogoStyleUsesAspectRatio(): void
    {
        $path = $this->createTempPng();
        try {
            $style = ImageStyleHelper::headerLogoStyle($path, 96);
            self::assertSame(ImageStyleHelper::cssPxToPt(96.0), $style['width']);
            self::assertGreaterThanOrEqual(1.0, $style['height']);
        } finally {
            @unlink($path);
        }
    }

    private function createTempPng(): string
    {
        $png = base64_decode(self::PNG_1X1, true);
        self::assertNotFalse($png);
        $tmp = tempnam(sys_get_temp_dir(), 'htw_style_png_') . '.png';
        file_put_contents($tmp, $png);

        return $tmp;
    }
}
