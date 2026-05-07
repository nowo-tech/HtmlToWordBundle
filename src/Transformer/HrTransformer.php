<?php

declare(strict_types=1);

namespace Nowo\HtmlToWordBundle\Transformer;

use DOMNode;
use Nowo\HtmlToWordBundle\Config\ResolvedConfig;
use PhpOffice\PhpWord\Element\AbstractContainer;

/**
 * @author Héctor Franco Aceituno <hectorfranco@nowo.tech>
 */
final class HrTransformer implements TransformerInterface
{
    public function supports(string $element): bool
    {
        return $element === 'hr';
    }

    public function getPriority(): int
    {
        return 35;
    }

    public function transform(
        DOMNode $node,
        AbstractContainer $container,
        ResolvedConfig $config,
        DocumentWalkerInterface $walker,
    ): void {
        $container->addTextRun([
            'borderBottom' => ['size' => 6, 'color' => '999999', 'space' => 1],
            'spaceAfter'   => 160,
        ])->addText(' ');
    }
}
