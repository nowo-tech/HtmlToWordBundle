<?php

declare(strict_types=1);

namespace Nowo\HtmlToWordBundle\Transformer;

use DOMElement;
use DOMNode;
use Nowo\HtmlToWordBundle\Builder\StyleMapper;
use Nowo\HtmlToWordBundle\Config\ResolvedConfig;
use PhpOffice\PhpWord\Element\AbstractContainer;

/**
 * @author Héctor Franco Aceituno <hectorfranco@nowo.tech>
 */
final readonly class BlockquoteTransformer implements TransformerInterface
{
    public function __construct(
        private StyleMapper $styleMapper,
    ) {
    }

    public function supports(string $element): bool
    {
        return $element === 'blockquote';
    }

    public function getPriority(): int
    {
        return 28;
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

        $pStyle = array_merge(
            $this->styleMapper->paragraphSpacing($config),
            [
                'indentation' => ['left' => 720],
            ],
        );

        $run = $container->addTextRun($pStyle);
        $walker->appendRichText($node, $run, $config);
    }
}
