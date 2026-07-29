<?php

declare(strict_types=1);

namespace Nowo\HtmlToWordBundle\Transformer;

use DOMElement;
use DOMNode;
use Nowo\HtmlToWordBundle\Builder\ImageResolverInterface;
use Nowo\HtmlToWordBundle\Builder\ImageStyleHelper;
use Nowo\HtmlToWordBundle\Builder\InlineComposer;
use Nowo\HtmlToWordBundle\Builder\StyleMapper;
use Nowo\HtmlToWordBundle\Config\ResolvedConfig;
use Nowo\HtmlToWordBundle\Exception\ImageResolveException;
use PhpOffice\PhpWord\Element\AbstractContainer;

/**
 * Block-level {@code <img>} (images inside paragraphs are handled by {@see InlineComposer}).
 *
 * @author Héctor Franco Aceituno <hectorfranco@nowo.tech>
 */
final readonly class ImageBlockTransformer implements TransformerInterface
{
    public function __construct(
        private ImageResolverInterface $imageResolver,
        private StyleMapper $styleMapper,
    ) {
    }

    public function supports(string $element): bool
    {
        return $element === 'img';
    }

    public function getPriority(): int
    {
        return 40;
    }

    public function transform(
        DOMNode $node,
        AbstractContainer $container,
        ResolvedConfig $config,
        DocumentWalkerInterface $walker,
    ): void {
        if (!$node instanceof DOMElement || strtolower($node->nodeName) !== 'img') {
            return;
        }

        $src = $node->getAttribute('src');
        if ($src === '') {
            return;
        }

        try {
            $path  = $this->imageResolver->resolveToTempPath($src, $config);
            $style = $this->imageStyle($node, $config);
            $maxW  = (float) $config->get('images.max_width', 600);
            $style = ImageStyleHelper::completeEmbeddingStyle($path, $style, $maxW);
            $container->addImage($path, $style);
        } catch (ImageResolveException) {
            $container->addText('[image]', $this->styleMapper->defaultFontStyle($config));
        }
    }

    /**
     * @return array<string, float|int|string>
     */
    private function imageStyle(DOMElement $node, ResolvedConfig $config): array
    {
        $style    = [];
        $maxWidth = (float) $config->get('images.max_width', 600);

        $w = $node->getAttribute('width');
        $h = $node->getAttribute('height');
        if ($w !== '' && is_numeric($w)) {
            $style['width'] = min((float) $w, $maxWidth);
        }
        if ($h !== '' && is_numeric($h)) {
            $style['height'] = (float) $h;
        }
        if (!isset($style['width']) && $node->hasAttribute('style') && preg_match('/width\s*:\s*([0-9.]+)px/i', $node->getAttribute('style'), $m)) {
            $style['width'] = min((float) $m[1], $maxWidth);
        }

        return $style;
    }
}
