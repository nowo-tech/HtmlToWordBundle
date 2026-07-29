<?php

declare(strict_types=1);

namespace Nowo\HtmlToWordBundle\Transformer;

use DOMNode;
use Nowo\HtmlToWordBundle\Builder\WordDocumentBuilder;
use Nowo\HtmlToWordBundle\Config\ResolvedConfig;
use PhpOffice\PhpWord\Element\AbstractContainer;

/**
 * Callback into {@see WordDocumentBuilder} for nested DOM walks.
 */
interface DocumentWalkerInterface
{
    public function dispatch(DOMNode $node, AbstractContainer $container, ResolvedConfig $config): void;

    /**
     * Inline / rich content (runs inside paragraphs, table cells, etc.).
     */
    public function appendRichText(DOMNode $node, AbstractContainer $container, ResolvedConfig $config): void;
}
