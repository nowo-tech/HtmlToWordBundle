<?php

declare(strict_types=1);

namespace Nowo\HtmlToWordBundle\Tests\Unit\Transformer;

use DOMDocument;
use DOMElement;
use DOMNode;
use Nowo\HtmlToWordBundle\Builder\ImageResolverInterface;
use Nowo\HtmlToWordBundle\Builder\InlineComposer;
use Nowo\HtmlToWordBundle\Builder\StyleMapper;
use Nowo\HtmlToWordBundle\Config\ResolvedConfig;
use Nowo\HtmlToWordBundle\Exception\ImageResolveException;
use Nowo\HtmlToWordBundle\Transformer\BlockquoteTransformer;
use Nowo\HtmlToWordBundle\Transformer\DivTransformer;
use Nowo\HtmlToWordBundle\Transformer\DocumentWalkerInterface;
use Nowo\HtmlToWordBundle\Transformer\HeadingTransformer;
use Nowo\HtmlToWordBundle\Transformer\ImageBlockTransformer;
use Nowo\HtmlToWordBundle\Transformer\ListTransformer;
use Nowo\HtmlToWordBundle\Transformer\ParagraphTransformer;
use Nowo\HtmlToWordBundle\Transformer\PreTransformer;
use Nowo\HtmlToWordBundle\Transformer\TableTransformer;
use PhpOffice\PhpWord\Element\AbstractContainer;
use PhpOffice\PhpWord\Element\Section;
use PhpOffice\PhpWord\PhpWord;
use PHPUnit\Framework\TestCase;

use const LIBXML_HTML_NODEFDTD;
use const LIBXML_HTML_NOIMPLIED;

final class BlockTransformersTest extends TestCase
{
    private const PNG_1X1 = 'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mP8z8BQDwAEhQGAhKmMIQAAAABJRU5ErkJggg==';

    public function testListTransformerSupportsAndPriorities(): void
    {
        $transformer = new ListTransformer($this->createInlineComposer());

        self::assertTrue($transformer->supports('ul'));
        self::assertTrue($transformer->supports('ol'));
        self::assertFalse($transformer->supports('p'));
        self::assertSame(45, $transformer->getPriority());
    }

    public function testListTransformerRendersNestedOrderedAndUnorderedLists(): void
    {
        $doc = new DOMDocument();
        $doc->loadHTML(
            '<ul><li>One<ol><li>Inner</li></ol><ul><li>Deep</li></ul></li><li>Two</li></ul>',
            LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD,
        );
        $ul = $doc->getElementsByTagName('ul')->item(0);
        self::assertInstanceOf(DOMElement::class, $ul);

        $section = $this->createSection();
        $walker  = $this->createWalker();
        $config  = ResolvedConfig::fromArray([]);

        (new ListTransformer($this->createInlineComposer()))->transform($ul, $section, $config, $walker);

        self::assertNotEmpty($section->getElements());
    }

    public function testListTransformerIgnoresNonElementNodes(): void
    {
        $doc     = new DOMDocument();
        $text    = $doc->createTextNode('plain');
        $section = $this->createSection();

        (new ListTransformer($this->createInlineComposer()))->transform(
            $text,
            $section,
            ResolvedConfig::fromArray([]),
            $this->createWalker(),
        );

        self::assertSame([], $section->getElements());
    }

    public function testListTransformerSkipsNonLiChildren(): void
    {
        $doc = new DOMDocument();
        $doc->loadHTML(
            '<ul> ignore <div>skip</div><li>Keep</li></ul>',
            LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD,
        );
        $ul = $doc->getElementsByTagName('ul')->item(0);
        self::assertInstanceOf(DOMElement::class, $ul);

        $section = $this->createSection();
        (new ListTransformer($this->createInlineComposer()))->transform(
            $ul,
            $section,
            ResolvedConfig::fromArray([]),
            $this->createWalker(),
        );
        self::assertNotEmpty($section->getElements());
    }

    public function testImageBlockTransformerHandlesMissingSrcAndResolveFailure(): void
    {
        $doc = new DOMDocument();
        $doc->loadHTML('<img src="" /><img src="bad://url" width="10" height="20" style="width:50px"/>', LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD);
        $images = $doc->getElementsByTagName('img');

        $resolver = $this->createMock(ImageResolverInterface::class);
        $resolver->method('resolveToTempPath')->willThrowException(new ImageResolveException('fail'));

        $transformer = new ImageBlockTransformer($resolver, new StyleMapper());
        $section     = $this->createSection();
        $config      = ResolvedConfig::fromArray(['images' => ['max_width' => 100]]);
        $walker      = $this->createWalker();

        $emptySrc = $images->item(0);
        self::assertInstanceOf(DOMElement::class, $emptySrc);
        $transformer->transform($emptySrc, $section, $config, $walker);

        $badSrc = $images->item(1);
        self::assertInstanceOf(DOMElement::class, $badSrc);
        $transformer->transform($badSrc, $section, $config, $walker);

        self::assertNotEmpty($section->getElements());
    }

    public function testImageBlockTransformerIgnoresNonImgNodes(): void
    {
        $doc     = new DOMDocument();
        $section = $this->createSection();
        $t       = new ImageBlockTransformer($this->createMock(ImageResolverInterface::class), new StyleMapper());

        $t->transform($doc->createTextNode('x'), $section, ResolvedConfig::fromArray([]), $this->createWalker());
        $t->transform($doc->createElement('div'), $section, ResolvedConfig::fromArray([]), $this->createWalker());

        self::assertSame([], $section->getElements());
    }

    public function testImageBlockTransformerReadsWidthFromStyle(): void
    {
        $doc = new DOMDocument();
        $doc->loadHTML('<img src="https://example.com/a.png" style="width:80px"/>', LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD);
        $img = $doc->getElementsByTagName('img')->item(0);
        self::assertInstanceOf(DOMElement::class, $img);

        $temp = tempnam(sys_get_temp_dir(), 'img');
        self::assertIsString($temp);
        $png = base64_decode(self::PNG_1X1, true);
        self::assertNotFalse($png);
        file_put_contents($temp, $png);

        $resolver = $this->createMock(ImageResolverInterface::class);
        $resolver->method('resolveToTempPath')->willReturn($temp);

        $section = $this->createSection();
        try {
            (new ImageBlockTransformer($resolver, new StyleMapper()))->transform(
                $img,
                $section,
                ResolvedConfig::fromArray(['images' => ['max_width' => 100]]),
                $this->createWalker(),
            );
            self::assertNotEmpty($section->getElements());
        } finally {
            @unlink($temp);
        }
    }

    public function testImageBlockTransformerEmbedsResolvedImage(): void
    {
        $doc = new DOMDocument();
        $doc->loadHTML('<img src="https://example.com/a.png" width="200" height="100"/>', LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD);
        $img = $doc->getElementsByTagName('img')->item(0);
        self::assertInstanceOf(DOMElement::class, $img);

        $temp = tempnam(sys_get_temp_dir(), 'img');
        self::assertIsString($temp);
        $png = base64_decode(self::PNG_1X1, true);
        self::assertNotFalse($png);
        file_put_contents($temp, $png);

        $resolver = $this->createMock(ImageResolverInterface::class);
        $resolver->method('resolveToTempPath')->willReturn($temp);

        $section = $this->createSection();
        try {
            (new ImageBlockTransformer($resolver, new StyleMapper()))->transform(
                $img,
                $section,
                ResolvedConfig::fromArray(['images' => ['max_width' => 100]]),
                $this->createWalker(),
            );
            self::assertNotEmpty($section->getElements());
        } finally {
            @unlink($temp);
        }
    }

    public function testParagraphTransformerAddsTextRun(): void
    {
        $doc = new DOMDocument();
        $doc->loadHTML('<p style="text-align:center">Hello</p>', LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD);
        $p = $doc->getElementsByTagName('p')->item(0);
        self::assertInstanceOf(DOMElement::class, $p);

        $section = $this->createSection();
        $walker  = $this->createMock(DocumentWalkerInterface::class);
        $walker->expects(self::once())->method('appendRichText');

        (new ParagraphTransformer(new StyleMapper()))->transform($p, $section, ResolvedConfig::fromArray([]), $walker);
        self::assertNotEmpty($section->getElements());
    }

    public function testParagraphTransformerIgnoresNonParagraph(): void
    {
        $doc     = new DOMDocument();
        $section = $this->createSection();
        $t       = new ParagraphTransformer(new StyleMapper());
        $t->transform($doc->createTextNode('x'), $section, ResolvedConfig::fromArray([]), $this->createWalker());
        $t->transform($doc->createElement('div'), $section, ResolvedConfig::fromArray([]), $this->createWalker());
        self::assertSame([], $section->getElements());
    }

    public function testPreTransformerAddsMonospaceRun(): void
    {
        $doc = new DOMDocument();
        $doc->loadHTML('<pre>code</pre>', LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD);
        $pre = $doc->getElementsByTagName('pre')->item(0);
        self::assertInstanceOf(DOMElement::class, $pre);

        $section = $this->createSection();
        $walker  = $this->createMock(DocumentWalkerInterface::class);
        $walker->expects(self::once())->method('appendRichText');

        (new PreTransformer())->transform($pre, $section, ResolvedConfig::fromArray([]), $walker);
        self::assertNotEmpty($section->getElements());
    }

    public function testPreTransformerIgnoresNonElementNode(): void
    {
        $section = $this->createSection();
        (new PreTransformer())->transform(
            (new DOMDocument())->createTextNode('x'),
            $section,
            ResolvedConfig::fromArray([]),
            $this->createWalker(),
        );
        self::assertSame([], $section->getElements());
    }

    public function testDivTransformerIgnoresNonElementAndSkipsWhitespaceText(): void
    {
        $doc     = new DOMDocument();
        $section = $this->createSection();
        $t       = new DivTransformer();
        $walker  = $this->createMock(DocumentWalkerInterface::class);
        $walker->expects(self::once())->method('dispatch');

        $t->transform($doc->createTextNode('x'), $section, ResolvedConfig::fromArray([]), $walker);

        $div = $doc->createElement('div');
        $div->appendChild($doc->createTextNode("  \n  "));
        $div->appendChild($doc->createElement('p', 'hi'));
        $t->transform($div, $section, ResolvedConfig::fromArray([]), $walker);
    }

    public function testHeadingTransformerGuards(): void
    {
        $doc     = new DOMDocument();
        $section = $this->createSection();
        $t       = new HeadingTransformer();
        $walker  = $this->createWalker();
        $cfg     = ResolvedConfig::fromArray([]);

        $t->transform($doc->createTextNode('x'), $section, $cfg, $walker);
        $t->transform($doc->createElement('h0'), $section, $cfg, $walker);
        self::assertSame([], $section->getElements());
    }

    public function testBlockquoteTransformerIgnoresNonElement(): void
    {
        $section = $this->createSection();
        (new BlockquoteTransformer(new StyleMapper()))->transform(
            (new DOMDocument())->createTextNode('x'),
            $section,
            ResolvedConfig::fromArray([]),
            $this->createWalker(),
        );
        self::assertSame([], $section->getElements());
    }

    public function testTableTransformerGuardsAndSkipsUnknownSections(): void
    {
        $doc     = new DOMDocument();
        $section = $this->createSection();
        $t       = new TableTransformer(new StyleMapper());
        $walker  = $this->createWalker();
        $cfg     = ResolvedConfig::fromArray([]);

        $t->transform($doc->createTextNode('x'), $section, $cfg, $walker);
        $t->transform($doc->createElement('div'), $section, $cfg, $walker);

        $table = $doc->createElement('table');
        $table->appendChild($doc->createElement('colgroup'));
        $tbody = $doc->createElement('tbody');
        $tr    = $doc->createElement('tr');
        $tr->appendChild($doc->createElement('td', 'cell'));
        $tbody->appendChild($tr);
        $table->appendChild($tbody);

        $t->transform($table, $section, $cfg, $walker);
        self::assertNotEmpty($section->getElements());
    }

    private function createSection(): Section
    {
        $phpWord = new PhpWord();

        return $phpWord->addSection();
    }

    private function createInlineComposer(): InlineComposer
    {
        return new InlineComposer(new StyleMapper(), $this->createMock(ImageResolverInterface::class));
    }

    private function createWalker(): DocumentWalkerInterface
    {
        return new class implements DocumentWalkerInterface {
            public function dispatch(DOMNode $node, AbstractContainer $container, ResolvedConfig $config): void
            {
            }

            public function appendRichText(DOMNode $node, AbstractContainer $container, ResolvedConfig $config): void
            {
            }
        };
    }
}
