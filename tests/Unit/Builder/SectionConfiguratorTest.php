<?php

declare(strict_types=1);

namespace Nowo\HtmlToWordBundle\Tests\Unit\Builder;

use Nowo\HtmlToWordBundle\Builder\SectionConfigurator;
use Nowo\HtmlToWordBundle\Config\ResolvedConfig;
use PHPUnit\Framework\TestCase;

final class SectionConfiguratorTest extends TestCase
{
    public function testSectionStyleLandscapeSwapsDimensions(): void
    {
        $s = new SectionConfigurator();
        $c = ResolvedConfig::fromArray([
            'page' => [
                'size'        => 'A4',
                'orientation' => 'landscape',
                'margins'     => ['top' => 1, 'right' => 2, 'bottom' => 3, 'left' => 4],
            ],
        ]);

        $style = $s->sectionStyle($c);
        self::assertSame('landscape', $style['orientation']);
        self::assertGreaterThan((int) $style['pageSizeH'], (int) $style['pageSizeW']);
    }

    public function testLetterPortraitUsesLetterDimensions(): void
    {
        $s = new SectionConfigurator();
        $c = ResolvedConfig::fromArray([
            'page' => [
                'size'        => 'LETTER',
                'orientation' => 'portrait',
                'margins'     => ['top' => 1, 'right' => 1, 'bottom' => 1, 'left' => 1],
            ],
        ]);

        $style = $s->sectionStyle($c);
        self::assertSame(12240, $style['pageSizeW']);
        self::assertSame(15840, $style['pageSizeH']);
    }

    public function testLegalPage(): void
    {
        $s = new SectionConfigurator();
        $c = ResolvedConfig::fromArray([
            'page' => [
                'size'        => 'LEGAL',
                'orientation' => 'portrait',
                'margins'     => ['top' => 1, 'right' => 1, 'bottom' => 1, 'left' => 1],
            ],
        ]);

        $style = $s->sectionStyle($c);
        self::assertSame(20160, $style['pageSizeH']);
    }
}
