<?php

declare(strict_types=1);

namespace Nowo\HtmlToWordBundle\Builder;

use DOMElement;
use DOMNode;
use Nowo\HtmlToWordBundle\Config\ResolvedConfig;
use Nowo\HtmlToWordBundle\Exception\ImageResolveException;
use Nowo\HtmlToWordBundle\Transformer\DocumentWalkerInterface;
use PhpOffice\PhpWord\Element\AbstractContainer;
use PhpOffice\PhpWord\Element\TextRun;

use const XML_ELEMENT_NODE;
use const XML_TEXT_NODE;

/**
 * Rich inline DOM nodes → PhpWord container / TextRun.
 *
 * @author Héctor Franco Aceituno <hectorfranco@nowo.tech>
 */
final readonly class InlineComposer
{
    public function __construct(
        private StyleMapper $styleMapper,
        private ImageResolverInterface $imageResolver,
    ) {
    }

    public function compose(DOMNode $parent, AbstractContainer $container, ResolvedConfig $config, DocumentWalkerInterface $walker): void
    {
        foreach ($parent->childNodes as $child) {
            $this->composeNode($child, $container, $config, $walker);
        }
    }

    /**
     * Compose a single node (public entry for list/table transformers).
     */
    public function composeInline(DOMNode $node, AbstractContainer $container, ResolvedConfig $config, DocumentWalkerInterface $walker): void
    {
        $this->composeNode($node, $container, $config, $walker);
    }

    private function composeNode(DOMNode $node, AbstractContainer $container, ResolvedConfig $config, DocumentWalkerInterface $walker): void
    {
        if ($node->nodeType === XML_TEXT_NODE) {
            $text = $node->textContent ?? '';
            if ($text !== '') {
                $container->addText($text, $this->styleMapper->defaultFontStyle($config));
            }

            return;
        }

        if ($node->nodeType !== XML_ELEMENT_NODE || !$node instanceof DOMElement) {
            return;
        }

        $tag       = strtolower($node->nodeName);
        $styleAttr = $node->getAttribute('style');

        match ($tag) {
            'strong', 'b'           => $this->wrapFontFlag($node, $container, $config, $walker, ['bold' => true]),
            'em', 'i'               => $this->wrapFontFlag($node, $container, $config, $walker, ['italic' => true]),
            'u'                     => $this->wrapFontFlag($node, $container, $config, $walker, ['underline' => 'single']),
            's', 'del', 'strike'    => $this->wrapFontFlag($node, $container, $config, $walker, ['strikethrough' => true]),
            'sup'                   => $this->wrapFontFlag($node, $container, $config, $walker, ['superScript' => true]),
            'sub'                   => $this->wrapFontFlag($node, $container, $config, $walker, ['subScript' => true]),
            'br'                    => $container->addTextBreak(),
            'a'                     => $this->addLink($node, $container, $config),
            'img'                   => $this->addImage($node, $container, $config),
            'span', 'small', 'mark' => $this->wrapSpan($node, $container, $config, $walker, $styleAttr),
            'code'                  => $this->wrapSpan($node, $container, $config, $walker, $styleAttr, ['name' => 'Courier New']),
            default                 => $this->wrapSpan($node, $container, $config, $walker, $styleAttr),
        };
    }

    /**
     * @param array<string, mixed> $extraFont
     */
    private function wrapFontFlag(DOMElement $node, AbstractContainer $container, ResolvedConfig $config, DocumentWalkerInterface $walker, array $extraFont): void
    {
        $run = $container instanceof TextRun ? $container : $container->addTextRun();
        $fs  = array_merge($this->styleMapper->defaultFontStyle($config), $extraFont);
        foreach ($node->childNodes as $c) {
            if ($c->nodeType === XML_TEXT_NODE) {
                $t = $c->textContent ?? '';
                if ($t !== '') {
                    $run->addText($t, $fs);
                }
            } elseif ($c instanceof DOMElement) {
                $nested = $run;
                $this->composeNode($c, $nested, $config, $walker);
            }
        }
    }

    /**
     * @param array<string, mixed> $extra
     */
    private function wrapSpan(DOMElement $node, AbstractContainer $container, ResolvedConfig $config, DocumentWalkerInterface $walker, string $styleAttr, array $extra = []): void
    {
        $fs  = array_merge($this->styleMapper->fontStyleFromInlineStyle($styleAttr, $config), $extra);
        $run = $container instanceof TextRun ? $container : $container->addTextRun();
        foreach ($node->childNodes as $c) {
            if ($c->nodeType === XML_TEXT_NODE) {
                $t = $c->textContent ?? '';
                if ($t !== '') {
                    $run->addText($t, $fs);
                }
            } elseif ($c instanceof DOMElement) {
                $this->composeNode($c, $run, $config, $walker);
            }
        }
    }

    private function addLink(DOMElement $node, AbstractContainer $container, ResolvedConfig $config): void
    {
        $href = $node->getAttribute('href');
        $text = $node->textContent ?? '';
        $run  = $container instanceof TextRun ? $container : $container->addTextRun();
        $font = $this->styleMapper->defaultFontStyle($config);
        if ($href !== '') {
            $internal = str_starts_with($href, '#');
            $run->addLink($href, $text, $font, [], $internal);
        } elseif ($text !== '') {
            $run->addText($text, $font);
        }
    }

    private function addImage(DOMElement $node, AbstractContainer $container, ResolvedConfig $config): void
    {
        $src = $node->getAttribute('src');
        if ($src === '') {
            return;
        }
        try {
            $path  = $this->imageResolver->resolveToTempPath($src, $config);
            $style = [];
            $w     = $node->getAttribute('width');
            $h     = $node->getAttribute('height');
            if ($w !== '' && is_numeric($w)) {
                $style['width'] = (float) $w;
            }
            if ($h !== '' && is_numeric($h)) {
                $style['height'] = (float) $h;
            }
            $maxW  = (float) $config->get('images.max_width', 600);
            $style = ImageStyleHelper::completeEmbeddingStyle($path, $style, $maxW);
            $container->addImage($path, $style);
        } catch (ImageResolveException) {
            $container->addText('[image]', $this->styleMapper->defaultFontStyle($config));
        }
    }
}
