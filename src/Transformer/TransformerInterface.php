<?php

declare(strict_types=1);

namespace Nowo\HtmlToWordBundle\Transformer;

use DOMNode;
use Nowo\HtmlToWordBundle\Config\ResolvedConfig;
use PhpOffice\PhpWord\Element\AbstractContainer;

interface TransformerInterface
{
    /**
     * Lowercase HTML tag name this transformer handles (e.g. p, table, h1).
     */
    public function supports(string $element): bool;

    /** Higher runs earlier when multiple transformers match. */
    public function getPriority(): int;

    public function transform(
        DOMNode $node,
        AbstractContainer $container,
        ResolvedConfig $config,
        DocumentWalkerInterface $walker,
    ): void;
}
