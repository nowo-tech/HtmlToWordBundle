<?php

declare(strict_types=1);

namespace Nowo\HtmlToWordBundle\Builder;

/**
 * Completes width/height for PHPWord. The library's image style uses **points (pt)**, not CSS pixels:
 * HTML {@code width}/{@code height} and {@see getimagesize()} are treated as **96 CSS px** and converted
 * with {@code pt = px × 72/96}. Skipping this makes Word receive hundreds of "points" for a normal photo
 * and pictures may not render as expected.
 *
 * @author Héctor Franco Aceituno <hectorfranco@nowo.tech>
 */
final class ImageStyleHelper
{
    /**
     * HTML / CSS pixels → typographic points (PhpWord default image unit).
     */
    public static function cssPxToPt(float $px): float
    {
        return $px * 72.0 / 96.0;
    }

    /**
     * @param array<string, float|int|string> $style width/height from HTML attributes (CSS px)
     * @param float $maxWidthPx value from profile {@code images.max_width} (CSS px)
     *
     * @return array<string, float|int|string>
     */
    public static function completeEmbeddingStyle(string $path, array $style, float $maxWidthPx): array
    {
        $info = @getimagesize($path);
        if ($info === false || $info[0] <= 0) {
            // `getimagesize` can fail (unusual path, stream wrappers, some Windows locks). HTML width/height
            // are still CSS px and must become points for PhpWord; without pt, images can appear empty in Word.
            $wPx = isset($style['width']) && is_numeric($style['width'])
                ? min((float) $style['width'], $maxWidthPx) : min(200.0, $maxWidthPx);
            $hPx = isset($style['height']) && is_numeric($style['height'])
                ? (float) $style['height'] : 120.0;

            return [
                'width'         => self::cssPxToPt($wPx),
                'height'        => self::cssPxToPt($hPx),
                'wrappingStyle' => 'inline',
            ];
        }

        $wSrc  = (float) $info[0];
        $hSrc  = (float) $info[1];
        $ratio = $hSrc / $wSrc;

        $attrW = isset($style['width']) && (float) $style['width'] > 0 ? (float) $style['width'] : null;
        $attrH = isset($style['height']) && (float) $style['height'] > 0 ? (float) $style['height'] : null;

        $wPx = null;
        $hPx = null;

        if ($attrW !== null && $attrH !== null) {
            $wPx = min($attrW, $maxWidthPx);
            $hPx = $attrH * ($wPx / $attrW);
        } elseif ($attrW !== null) {
            $wPx = min($attrW, $maxWidthPx);
            $hPx = $wPx * $ratio;
        } elseif ($attrH !== null) {
            $hPx = $attrH;
            $wPx = min($hPx / $ratio, $maxWidthPx);
        } else {
            $wPx = min($wSrc, $maxWidthPx);
            $hPx = $wPx * $ratio;
        }

        $wPx = max(1.0, $wPx);
        $hPx = max(1.0, $hPx);

        return [
            'width'         => self::cssPxToPt($wPx),
            'height'        => self::cssPxToPt($hPx),
            'wrappingStyle' => 'inline',
        ];
    }

    /**
     * Header/footer logo: {@code header.logo_width} is interpreted as **CSS px**, converted to pt for PhpWord.
     *
     * @return array{width: float, height: float, wrappingStyle: string}
     */
    public static function headerLogoStyle(string $path, int $targetWidthPx): array
    {
        $targetPx = max(1, $targetWidthPx);
        $widthPt  = self::cssPxToPt((float) $targetPx);

        $info = @getimagesize($path);
        if ($info === false || $info[0] <= 0) {
            return [
                'width'         => $widthPt,
                'height'        => $widthPt,
                'wrappingStyle' => 'inline',
            ];
        }

        $heightPt = round($widthPt * ($info[1] / $info[0]));

        return [
            'width'         => $widthPt,
            'height'        => (float) max(1, $heightPt),
            'wrappingStyle' => 'inline',
        ];
    }
}
