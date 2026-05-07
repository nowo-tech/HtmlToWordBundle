<?php

declare(strict_types=1);

namespace Nowo\HtmlToWordBundle\Transformer;

use DOMElement;
use DOMNode;
use Nowo\HtmlToWordBundle\Builder\InlineComposer;
use Nowo\HtmlToWordBundle\Config\ResolvedConfig;
use PhpOffice\PhpWord\Element\AbstractContainer;
use PhpOffice\PhpWord\Style\ListItem as ListItemStyle;

use function count;

/**
 * Multilevel {@code ul}/{@code ol} lists using {@see \PhpOffice\PhpWord\Element\ListItemRun}.
 *
 * @author Héctor Franco Aceituno <hectorfranco@nowo.tech>
 */
final readonly class ListTransformer implements TransformerInterface
{
    public function __construct(
        private InlineComposer $inlineComposer,
    ) {
    }

    public function supports(string $element): bool
    {
        return $element === 'ul' || $element === 'ol';
    }

    public function getPriority(): int
    {
        return 45;
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
        if ($tag === 'ul') {
            $this->renderUnordered($node, $container, $config, $walker, 0);

            return;
        }

        if ($tag === 'ol') {
            $this->renderOrdered($node, $container, $config, $walker, 0);
        }
    }

    private function renderUnordered(DOMElement $list, AbstractContainer $container, ResolvedConfig $config, DocumentWalkerInterface $walker, int $depth): void
    {
        $style = ['listType' => ListItemStyle::TYPE_BULLET_FILLED];
        $this->walkList($list, $container, $config, $walker, $depth, $style);
    }

    private function renderOrdered(DOMElement $list, AbstractContainer $container, ResolvedConfig $config, DocumentWalkerInterface $walker, int $depth): void
    {
        $style = ['listType' => ListItemStyle::TYPE_NUMBER];
        $this->walkList($list, $container, $config, $walker, $depth, $style);
    }

    /**
     * @param array<string, mixed> $listStyle
     */
    private function walkList(DOMElement $list, AbstractContainer $container, ResolvedConfig $config, DocumentWalkerInterface $walker, int $depth, array $listStyle): void
    {
        foreach ($list->childNodes as $child) {
            if (!$child instanceof DOMElement || strtolower($child->nodeName) !== 'li') {
                continue;
            }

            $this->renderLi($child, $container, $config, $walker, $depth, $listStyle);
        }
    }

    /**
     * @param array<string, mixed> $listStyle
     */
    private function renderLi(DOMElement $li, AbstractContainer $container, ResolvedConfig $config, DocumentWalkerInterface $walker, int $depth, array $listStyle): void
    {
        $nodes = [];
        foreach ($li->childNodes as $c) {
            $nodes[] = $c;
        }

        $i = 0;
        $n = count($nodes);
        while ($i < $n) {
            $current = $nodes[$i];
            if ($current instanceof DOMElement) {
                $tn = strtolower($current->nodeName);
                if ($tn === 'ul') {
                    $this->renderUnordered($current, $container, $config, $walker, $depth + 1);
                    ++$i;

                    continue;
                }
                if ($tn === 'ol') {
                    $this->renderOrdered($current, $container, $config, $walker, $depth + 1);
                    ++$i;

                    continue;
                }
            }

            $buffer = [];
            while ($i < $n) {
                $c = $nodes[$i];
                if ($c instanceof DOMElement) {
                    $tn = strtolower($c->nodeName);
                    if ($tn === 'ul' || $tn === 'ol') {
                        break;
                    }
                }
                $buffer[] = $c;
                ++$i;
            }

            if ($buffer === []) {
                continue;
            }

            $run = $container->addListItemRun($depth, $listStyle);
            foreach ($buffer as $fragment) {
                $this->inlineComposer->composeInline($fragment, $run, $config, $walker);
            }
        }
    }
}
