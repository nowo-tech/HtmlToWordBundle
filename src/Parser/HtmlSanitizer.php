<?php

declare(strict_types=1);

namespace Nowo\HtmlToWordBundle\Parser;

use DOMAttr;
use DOMDocument;
use DOMXPath;

/**
 * Removes scripts/iframes and unsafe attributes before parsing.
 *
 * @author Héctor Franco Aceituno <hectorfranco@nowo.tech>
 */
final class HtmlSanitizer
{
    public function sanitize(string $html): string
    {
        $html = preg_replace('#<script\b[^>]*>.*?</script>#is', '', $html) ?? $html;
        $html = preg_replace('#<style\b[^>]*>.*?</style>#is', '', $html) ?? $html;

        return preg_replace('#<(iframe|object|embed)\b[^>]*>.*?</\1>#is', '', $html) ?? $html;
    }

    /**
     * Strip event-handler attributes from HTML string via DOM (best-effort).
     */
    public function sanitizeDom(DOMDocument $dom): void
    {
        $xpath = new DOMXPath($dom);
        foreach ($xpath->query('//@*') ?: [] as $attr) {
            // XPath //@* always yields DOMAttr in practice; guard kept for static analysis.
            // @codeCoverageIgnoreStart
            if (!$attr instanceof DOMAttr) {
                continue;
            }
            // @codeCoverageIgnoreEnd
            $n = $attr->name;
            if (str_starts_with(strtolower($n), 'on')) {
                $attr->ownerElement?->removeAttribute($n);
            }
        }
    }
}
