<?php

declare(strict_types=1);

namespace Nowo\HtmlToWordBundle\Transformer;

use DOMElement;
use DOMNode;
use Nowo\HtmlToWordBundle\Config\ResolvedConfig;
use PhpOffice\PhpWord\Element\AbstractContainer;
use PhpOffice\PhpWord\Element\TextRun;

/**
 * Maps {@code h1}–{@code h6} to PHPWord {@see \PhpOffice\PhpWord\Element\Title} (rich TextRun when needed).
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

        $depth = (int) $m[1];
        $run   = new TextRun();
        $walker->appendRichText($node, $run, $config);
        $container->addTitle($run, $depth);
    }
}
