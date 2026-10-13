<?php

declare(strict_types=1);

namespace Nowo\HtmlToWordBundle\Transformer;

use DOMElement;
use DOMNode;
use Nowo\HtmlToWordBundle\Config\ResolvedConfig;
use PhpOffice\PhpWord\Element\AbstractContainer;
use PhpOffice\PhpWord\Element\TextRun;
use PhpOffice\PhpWord\Element\Title;

use function is_string;
use function trim;

/**
 * Maps {@code h1}–{@code h6} via {@code styles.heading_map}: {@code HeadingN} values become PHPWord
 * {@see Title} elements of depth N; any other value is applied as a Word paragraph style name.
 *
 * @author Héctor Franco Aceituno <hectorfranco@nowo.tech>
 */
final class HeadingTransformer implements TransformerInterface
{
    public function supports(string $element): bool
    {
        return (bool) preg_match('/^h[1-6]$/', $element);
    }

    public function getPriority(): int
    {
        return 30;
    }

    public function transform(
        DOMNode $node,
        AbstractContainer $container,
        ResolvedConfig $config,
        DocumentWalkerInterface $walker,
    ): void {
        if (!$node instanceof DOMElement) {
            return;
        }

        $tag = strtolower($node->nodeName);
        if (!preg_match('/^h([1-6])$/', $tag, $m)) {
            return;
        }

        $depth  = (int) $m[1];
        $mapped = $config->get('styles.heading_map.' . $tag);
        $style  = is_string($mapped) ? trim($mapped) : '';
        $hm     = [];

        // Built-in heading styles keep PHPWord titles (outline level / TOC); h2: Heading1 re-levels the heading.
        if ($style === '' || preg_match('/^Heading([1-9])$/', $style, $hm)) {
            $run = new TextRun();
            $walker->appendRichText($node, $run, $config);
            $container->addTitle($run, isset($hm[1]) ? (int) $hm[1] : $depth);

            return;
        }

        // Any other value is a Word paragraph style name (must exist in the document/template).
        $walker->appendRichText($node, $container->addTextRun($style), $config);
    }
}
