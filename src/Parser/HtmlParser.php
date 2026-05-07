<?php

declare(strict_types=1);

namespace Nowo\HtmlToWordBundle\Parser;

use DOMDocument;
use Masterminds\HTML5;

/**
 * Parses HTML into DOMDocument (full document with body wrapper).
 *
 * @author Héctor Franco Aceituno <hectorfranco@nowo.tech>
 */
final class HtmlParser
{
    public function parse(string $html): DOMDocument
    {
        $html5 = new HTML5(['disable_html_ns' => true]);
        $wrap  = '<!DOCTYPE html><html><head><meta charset="UTF-8"></head><body>' . $html . '</body></html>';

        return @$html5->loadHTML($wrap);
    }
}
