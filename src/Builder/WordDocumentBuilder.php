<?php

declare(strict_types=1);

namespace Nowo\HtmlToWordBundle\Builder;

use DOMElement;
use DOMNode;
use Nowo\HtmlToWordBundle\Config\ResolvedConfig;
use Nowo\HtmlToWordBundle\Engine\PhpWordEngine;
use Nowo\HtmlToWordBundle\Exception\HtmlParseException;
use Nowo\HtmlToWordBundle\Exception\UnsupportedElementException;
use Nowo\HtmlToWordBundle\Model\WordDocument;
use Nowo\HtmlToWordBundle\Parser\HtmlParser;
use Nowo\HtmlToWordBundle\Parser\HtmlSanitizer;
use Nowo\HtmlToWordBundle\Parser\RemoteHttpImageInliner;
use Nowo\HtmlToWordBundle\Transformer\DocumentWalkerInterface;
use Nowo\HtmlToWordBundle\Transformer\TransformerChain;
use Nowo\HtmlToWordBundle\Transformer\TransformerInterface;
use PhpOffice\PhpWord\Element\AbstractContainer;
use PhpOffice\PhpWord\PhpWord;

use function sprintf;

use const XML_ELEMENT_NODE;
use const XML_TEXT_NODE;

/**
 * HTML → PHPWord conversion pipeline.
 *
 * @author Héctor Franco Aceituno <hectorfranco@nowo.tech>
 */
final readonly class WordDocumentBuilder implements DocumentWalkerInterface
{
    public function __construct(
        private HtmlSanitizer $sanitizer,
        private RemoteHttpImageInliner $remoteHttpImageInliner,
        private HtmlParser $parser,
        private SectionConfigurator $sectionConfigurator,
        private HeaderFooterBuilder $headerFooterBuilder,
        private TransformerChain $transformerChain,
        private InlineComposer $inlineComposer,
    ) {
    }

    public function build(string $html, ResolvedConfig $config): WordDocument
    {
        $clean = $this->sanitizer->sanitize($html);
        $clean = $this->remoteHttpImageInliner->inlineRemoteImages($clean, $config);
        $dom   = $this->parser->parse($clean);
        $this->sanitizer->sanitizeDom($dom);
        $body = $dom->getElementsByTagName('body')->item(0);
        // Masterminds HTML5 + HtmlParser always wrap fragments with <body>; kept as safety net.
        // @codeCoverageIgnoreStart
        if (!$body instanceof DOMElement) {
            throw new HtmlParseException('Invalid HTML: missing <body>.');
        }
        // @codeCoverageIgnoreEnd

        $phpWord = new PhpWord();
        $section = $phpWord->addSection($this->sectionConfigurator->sectionStyle($config));
        $this->headerFooterBuilder->apply($section, $config);

        foreach ($body->childNodes as $child) {
            if ($child->nodeType === XML_TEXT_NODE && trim($child->textContent ?? '') === '') {
                continue;
            }
            $this->dispatch($child, $section, $config);
        }

        // Temp paths from RemoteHttpImageInliner must survive until IOFactory::createWriter()->save()
        // copies bytes into the DOCX; cleanup runs in DocxExporter after save.

        return new WordDocument($phpWord, $config, PhpWordEngine::NAME);
    }

    public function dispatch(DOMNode $node, AbstractContainer $container, ResolvedConfig $config): void
    {
        if ($node->nodeType === XML_TEXT_NODE) {
            $t = trim($node->textContent ?? '');
            if ($t !== '') {
                $container->addText($t);
            }

            return;
        }

        if ($node->nodeType !== XML_ELEMENT_NODE || !$node instanceof DOMElement) {
            return;
        }

        $tag = strtolower($node->nodeName);
        $t   = $this->transformerChain->firstSupporting($tag);
        if ($t instanceof TransformerInterface) {
            $t->transform($node, $container, $config, $this);

            return;
        }

        if ($config->strictMode()) {
            throw new UnsupportedElementException(sprintf('No transformer for <%s> (strict mode).', $tag));
        }
    }

    public function appendRichText(DOMNode $node, AbstractContainer $container, ResolvedConfig $config): void
    {
        $this->inlineComposer->compose($node, $container, $config, $this);
    }
}
