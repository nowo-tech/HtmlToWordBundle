<?php

declare(strict_types=1);

namespace Nowo\HtmlToWordBundle\Transformer;

use DOMElement;
use DOMNode;
use Nowo\HtmlToWordBundle\Config\ResolvedConfig;
use PhpOffice\PhpWord\Element\AbstractContainer;

/**
 * Monospace block for {@code <pre>} (inline {@code <code>} is handled by {@see \Nowo\HtmlToWordBundle\Builder\InlineComposer}).
 *
 * @author Héctor Franco Aceituno <hectorfranco@nowo.tech>
 */
final class PreTransformer implements TransformerInterface
{
    public function supports(string $element): bool
    {
        return $element === 'pre';
    }

    public function getPriority(): int
    {
        return 27;
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

        $run = $container->addTextRun([
            'shading'    => ['fill' => 'F2F2F2'],
            'spaceAfter' => 120,
        ]);
        $walker->appendRichText($node, $run, $config);
    }
}
