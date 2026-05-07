<?php

declare(strict_types=1);

namespace Nowo\HtmlToWordBundle\Transformer;

use DOMElement;
use DOMNode;
use Nowo\HtmlToWordBundle\Config\ResolvedConfig;
use PhpOffice\PhpWord\Element\AbstractContainer;

use function in_array;

use const XML_TEXT_NODE;

/**
 * Handles {@code <div>} page-break hints and generic block containers ({@code article}, {@code section}, …).
 *
 * @author Héctor Franco Aceituno <hectorfranco@nowo.tech>
 */
final class DivTransformer implements TransformerInterface
{
    /** @var list<string> */
    private const CONTAINER_TAGS = ['div', 'article', 'section', 'main', 'nav', 'figure'];

    public function supports(string $element): bool
    {
        return in_array($element, self::CONTAINER_TAGS, true);
    }

    public function getPriority(): int
    {
        return 38;
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

        if ($this->isPageBreakContainer($node)) {
            $container->addPageBreak();

            return;
        }

        foreach ($node->childNodes as $child) {
            if ($child->nodeType === XML_TEXT_NODE && trim($child->textContent ?? '') === '') {
                continue;
            }
            $walker->dispatch($child, $container, $config);
        }
    }

    private function isPageBreakContainer(DOMElement $node): bool
    {
        if ($node->hasAttribute('data-page-break')) {
            return true;
        }

        $class = $node->getAttribute('class');

        return $class !== '' && str_contains($class, 'page-break');
    }
}
