<?php

declare(strict_types=1);

namespace Nowo\HtmlToWordBundle\Builder;

use Nowo\HtmlToWordBundle\Config\ResolvedConfig;

/**
 * Builds PhpWord section style array (margins, orientation, paper size in twips).
 *
 * @author Héctor Franco Aceituno <hectorfranco@nowo.tech>
 */
final class SectionConfigurator
{
    /** A4 portrait (twips, OOXML). */
    private const A4_W = 11906;

    private const A4_H = 16838;

    /** US Letter */
    private const LETTER_W = 12240;

    private const LETTER_H = 15840;

    /** Legal */
    private const LEGAL_W = 12240;

    private const LEGAL_H = 20160;

    /**
     * @return array<string, int|string>
     */
    public function sectionStyle(ResolvedConfig $config): array
    {
        $mTop    = (int) $config->get('page.margins.top', 1440);
        $mRight  = (int) $config->get('page.margins.right', 1440);
        $mBottom = (int) $config->get('page.margins.bottom', 1440);
        $mLeft   = (int) $config->get('page.margins.left', 1440);

        $orientation = strtolower((string) $config->get('page.orientation', 'portrait'));
        $landscape   = $orientation === 'landscape';

        $size    = strtoupper((string) $config->get('page.size', 'A4'));
        [$w, $h] = match ($size) {
            'LETTER' => [self::LETTER_W, self::LETTER_H],
            'LEGAL'  => [self::LEGAL_W, self::LEGAL_H],
            'CUSTOM' => [
                (int) ($config->get('page.custom_width') ?? self::A4_W),
                (int) ($config->get('page.custom_height') ?? self::A4_H),
            ],
            default => [self::A4_W, self::A4_H],
        };

        if ($landscape) {
            [$w, $h] = [$h, $w];
        }

        return [
            'marginTop'    => $mTop,
            'marginRight'  => $mRight,
            'marginBottom' => $mBottom,
            'marginLeft'   => $mLeft,
            'orientation'  => $landscape ? 'landscape' : 'portrait',
            'pageSizeW'    => $w,
            'pageSizeH'    => $h,
        ];
    }
}
