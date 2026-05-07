<?php

declare(strict_types=1);

namespace Nowo\HtmlToWordBundle\Builder;

use Nowo\HtmlToWordBundle\Config\ResolvedConfig;

use function sprintf;

/**
 * Maps CSS inline styles / tag semantics to PhpWord font + paragraph style arrays.
 *
 * @author Héctor Franco Aceituno <hectorfranco@nowo.tech>
 */
final class StyleMapper
{
    /**
     * @return array<string, mixed>
     */
    public function defaultFontStyle(ResolvedConfig $config): array
    {
        return [
            'name' => (string) $config->get('fonts.default', 'Arial'),
            'size' => (float) $config->get('fonts.default_size', 11),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function paragraphSpacing(ResolvedConfig $config): array
    {
        return [
            'spaceBefore' => (int) $config->get('styles.paragraph_spacing.before', 0),
            'spaceAfter'  => (int) $config->get('styles.paragraph_spacing.after', 160),
        ];
    }

    /**
     * Parses style attribute string into PhpWord font style fragment.
     *
     * @return array<string, mixed>
     */
    public function fontStyleFromInlineStyle(?string $styleAttr, ResolvedConfig $config): array
    {
        $base = $this->defaultFontStyle($config);
        if ($styleAttr === null || $styleAttr === '') {
            return $base;
        }

        $decls = $this->parseCssDeclarations($styleAttr);
        if (isset($decls['font-family'])) {
            $base['name'] = $this->firstFontFamily($decls['font-family']) ?? $base['name'];
        }
        if (isset($decls['font-size'])) {
            $pt = $this->cssSizeToPoints($decls['font-size']);
            if ($pt !== null) {
                $base['size'] = $pt;
            }
        }
        if (isset($decls['color'])) {
            $hex = $this->normalizeColor($decls['color']);
            if ($hex !== null) {
                $base['color'] = $hex;
            }
        }
        if (isset($decls['background-color'])) {
            $hex = $this->normalizeColor($decls['background-color']);
            if ($hex !== null) {
                $base['bgColor'] = $hex;
            }
        }

        return $base;
    }

    /**
     * @return array<string, mixed>
     */
    public function paragraphStyleFromInlineStyle(?string $styleAttr, ResolvedConfig $config): array
    {
        $p = $this->paragraphSpacing($config);
        if ($styleAttr === null || $styleAttr === '') {
            return $p;
        }
        $decls = $this->parseCssDeclarations($styleAttr);
        if (isset($decls['text-align'])) {
            $p['alignment'] = match (strtolower($decls['text-align'])) {
                'center'  => 'center',
                'right'   => 'right',
                'justify' => 'both',
                default   => 'left',
            };
        }

        return $p;
    }

    /**
     * Hex background for PHPWord cell shading (no leading {@code #}), extracted from a {@code style="..."} attribute.
     */
    public function tableCellBackgroundHex(?string $styleAttr): ?string
    {
        if ($styleAttr === null || $styleAttr === '') {
            return null;
        }

        if (!preg_match('/background(?:-color)?\s*:\s*([^;]+)/i', $styleAttr, $m)) {
            return null;
        }

        $hex = $this->normalizeColor(trim($m[1]));
        if ($hex === null) {
            return null;
        }

        return strtoupper(ltrim($hex, '#'));
    }

    /**
     * @return array<string, string>
     */
    private function parseCssDeclarations(string $css): array
    {
        $out = [];
        foreach (explode(';', $css) as $chunk) {
            $chunk = trim($chunk);
            if ($chunk === '' || !str_contains($chunk, ':')) {
                continue;
            }
            [$k, $v]                   = explode(':', $chunk, 2);
            $out[strtolower(trim($k))] = trim($v);
        }

        return $out;
    }

    private function firstFontFamily(string $raw): ?string
    {
        $parts = preg_split('/\s*,\s*/', $raw) ?: [];
        foreach ($parts as $p) {
            $p = trim($p, " \t\n\r\0\x0B\"'");
            if ($p !== '') {
                return $p;
            }
        }

        return null;
    }

    private function cssSizeToPoints(string $value): ?float
    {
        $value = trim($value);
        if ($value === '') {
            return null;
        }
        if (str_ends_with($value, 'pt')) {
            return (float) str_replace('pt', '', $value);
        }
        if (str_ends_with($value, 'px')) {
            return round(((float) str_replace('px', '', $value)) / 1.333333, 2);
        }
        if (str_ends_with($value, 'rem') || str_ends_with($value, 'em')) {
            $n = (float) preg_replace('/[^0-9.+-]/', '', $value);

            return round($n * 11, 2);
        }

        return (float) $value;
    }

    private function normalizeColor(string $raw): ?string
    {
        $raw = trim($raw);
        if ($raw === '') {
            return null;
        }
        if (str_starts_with($raw, '#')) {
            return strtoupper(substr($raw, 0, 7));
        }
        if (str_starts_with($raw, 'rgb') && preg_match('/rgba?\(\s*(\d+)\s*,\s*(\d+)\s*,\s*(\d+)/', $raw, $m)) {
            return sprintf('%02X%02X%02X', (int) $m[1], (int) $m[2], (int) $m[3]);
        }

        return null;
    }
}
