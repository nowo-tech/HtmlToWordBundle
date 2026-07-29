<?php

declare(strict_types=1);

namespace Nowo\HtmlToWordBundle\Tests\Unit\Builder;

use Nowo\HtmlToWordBundle\Builder\StyleMapper;
use Nowo\HtmlToWordBundle\Config\ResolvedConfig;
use PHPUnit\Framework\TestCase;

final class StyleMapperTest extends TestCase
{
    public function testDefaultFontStyle(): void
    {
        $m = new StyleMapper();
        $c = ResolvedConfig::fromArray([
            'fonts' => ['default' => 'Calibri', 'default_size' => 12],
        ]);

        $fs = $m->defaultFontStyle($c);
        self::assertSame('Calibri', $fs['name']);
        self::assertSame(12.0, $fs['size']);
    }

    public function testParagraphSpacing(): void
    {
        $m = new StyleMapper();
        $c = ResolvedConfig::fromArray([
            'styles' => ['paragraph_spacing' => ['before' => 10, 'after' => 20]],
        ]);

        $p = $m->paragraphSpacing($c);
        self::assertSame(10, $p['spaceBefore']);
        self::assertSame(20, $p['spaceAfter']);
    }

    public function testFontStyleFromInlineStyleParsesColorAndSize(): void
    {
        $m     = new StyleMapper();
        $c     = ResolvedConfig::fromArray([]);
        $style = 'color: #ff0000; font-size: 12pt; font-family: Georgia, serif';
        $fs    = $m->fontStyleFromInlineStyle($style, $c);

        self::assertSame('#FF0000', $fs['color']);
        self::assertSame(12.0, $fs['size']);
        self::assertSame('Georgia', $fs['name']);
    }

    public function testTableCellBackgroundHex(): void
    {
        $m = new StyleMapper();
        self::assertSame('EFEFEF', $m->tableCellBackgroundHex('background-color: #efefef'));
        self::assertNull($m->tableCellBackgroundHex(''));
        self::assertSame('FF0000', $m->tableCellBackgroundHex('background: rgb(255, 0, 0)'));
    }

    public function testParagraphStyleFromInlineAlignments(): void
    {
        $m = new StyleMapper();
        $c = ResolvedConfig::fromArray([]);
        self::assertSame('center', $m->paragraphStyleFromInlineStyle('text-align: center', $c)['alignment']);
        self::assertSame('right', $m->paragraphStyleFromInlineStyle('text-align: right', $c)['alignment']);
        self::assertSame('both', $m->paragraphStyleFromInlineStyle('text-align: justify', $c)['alignment']);
        self::assertSame('left', $m->paragraphStyleFromInlineStyle('text-align: left', $c)['alignment']);
        $base = $m->paragraphStyleFromInlineStyle('', $c);
        self::assertArrayNotHasKey('alignment', $base);
    }

    public function testFontStyleSupportsRgbEmPxBackground(): void
    {
        $m = new StyleMapper();
        $c = ResolvedConfig::fromArray([
            'fonts' => ['default' => 'Arial', 'default_size' => 11],
        ]);

        $rgb = $m->fontStyleFromInlineStyle('color: rgb(10, 20, 30)', $c);
        self::assertSame('0A141E', $rgb['color']);

        $bg = $m->fontStyleFromInlineStyle('background-color: #ABCDEF', $c);
        self::assertSame('#ABCDEF', $bg['bgColor']);

        $em = $m->fontStyleFromInlineStyle('font-size: 2em', $c);
        self::assertSame(22.0, $em['size']);

        $px = $m->fontStyleFromInlineStyle('font-size: 16px', $c);
        self::assertSame(12.0, $px['size']);

        $plain = $m->fontStyleFromInlineStyle('font-size: 14', $c);
        self::assertSame(14.0, $plain['size']);
    }

    public function testFontStyleEmptyInlineReturnsDefaults(): void
    {
        $m = new StyleMapper();
        $c = ResolvedConfig::fromArray(['fonts' => ['default' => 'Arial', 'default_size' => 11]]);
        self::assertSame($m->defaultFontStyle($c), $m->fontStyleFromInlineStyle('', $c));
    }

    public function testTableCellBackgroundHexRejectsInvalidColor(): void
    {
        $m = new StyleMapper();
        self::assertNull($m->tableCellBackgroundHex('background-color: transparent'));
        self::assertNull($m->tableCellBackgroundHex('background-color: '));
    }

    public function testFontStyleSkipsEmptyCssChunksAndEmptyFamilies(): void
    {
        $m = new StyleMapper();
        $c = ResolvedConfig::fromArray(['fonts' => ['default' => 'Arial', 'default_size' => 11]]);

        $fs = $m->fontStyleFromInlineStyle('color: #112233;; font-family: , , ; font-size:   ', $c);
        self::assertSame('#112233', $fs['color']);
        self::assertSame('Arial', $fs['name']);
        self::assertSame(11.0, $fs['size']);
    }

    public function testNormalizeColorRejectsEmptyAndUnknown(): void
    {
        $m  = new StyleMapper();
        $c  = ResolvedConfig::fromArray([]);
        $fs = $m->fontStyleFromInlineStyle('color: ; background-color: orange', $c);
        self::assertArrayNotHasKey('color', $fs);
        self::assertArrayNotHasKey('bgColor', $fs);
    }
}
