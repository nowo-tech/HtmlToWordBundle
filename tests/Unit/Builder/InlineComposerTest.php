<?php

declare(strict_types=1);

namespace Nowo\HtmlToWordBundle\Tests\Unit\Builder;

use DOMDocument;
use DOMElement;
use DOMNode;
use Nowo\HtmlToWordBundle\Builder\ImageResolver;
use Nowo\HtmlToWordBundle\Builder\ImageResolverInterface;
use Nowo\HtmlToWordBundle\Builder\InlineComposer;
use Nowo\HtmlToWordBundle\Builder\StyleMapper;
use Nowo\HtmlToWordBundle\Config\ResolvedConfig;
use Nowo\HtmlToWordBundle\Exception\ImageResolveException;
use Nowo\HtmlToWordBundle\Transformer\DocumentWalkerInterface;
use PhpOffice\PhpWord\Element\AbstractContainer;
use PhpOffice\PhpWord\PhpWord;
use PHPUnit\Framework\TestCase;

final class InlineComposerTest extends TestCase
{
    private const PNG_1X1 = 'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mP8z8BQDwAEhQGAhKmMIQAAAABJRU5ErkJggg==';

    private function walker(): DocumentWalkerInterface
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

    private function config(): ResolvedConfig
    {
        return ResolvedConfig::fromArray([
            'strict_mode' => false,
            'fonts'       => ['default' => 'Arial', 'default_size' => 11],
            'images'      => ['max_width' => 600],
        ]);
    }

    public function testFontTagUsesSpanPath(): void
    {
        $c   = new InlineComposer(new StyleMapper(), new ImageResolver());
        $dom = new DOMDocument();
        $dom->loadHTML('<body><p><font color="#112233">Legacy</font></p></body>');
        $p = $dom->getElementsByTagName('p')->item(0);
        self::assertInstanceOf(DOMElement::class, $p);

        $pw      = new PhpWord();
        $section = $pw->addSection();
        $c->compose($p, $section, $this->config(), $this->walker());
        self::assertCount(1, $pw->getSections());
    }

    public function testNestedFormattingAndBreak(): void
    {
        $c   = new InlineComposer(new StyleMapper(), new ImageResolver());
        $dom = new DOMDocument();
        $dom->loadHTML('<body><p><strong><em>x</em></strong><br/><a href="">no href</a></p></body>');
        $p = $dom->getElementsByTagName('p')->item(0);
        self::assertInstanceOf(DOMElement::class, $p);

        $pw      = new PhpWord();
        $section = $pw->addSection();
        $c->compose($p, $section, $this->config(), $this->walker());
        self::assertCount(1, $pw->getSections());
    }

    public function testIgnoresCommentNodes(): void
    {
        $c   = new InlineComposer(new StyleMapper(), new ImageResolver());
        $dom = new DOMDocument();
        $dom->loadHTML('<body><p>a<!--c-->b</p></body>');
        $p = $dom->getElementsByTagName('p')->item(0);
        self::assertInstanceOf(DOMElement::class, $p);

        $pw      = new PhpWord();
        $section = $pw->addSection();
        $c->compose($p, $section, $this->config(), $this->walker());
        self::assertCount(1, $pw->getSections());
    }

    public function testNestedSpanElements(): void
    {
        $c   = new InlineComposer(new StyleMapper(), new ImageResolver());
        $dom = new DOMDocument();
        $dom->loadHTML('<body><p><span style="color:#00ff00">outer <em>inner</em></span></p></body>');
        $p = $dom->getElementsByTagName('p')->item(0);
        self::assertInstanceOf(DOMElement::class, $p);

        $pw      = new PhpWord();
        $section = $pw->addSection();
        $c->compose($p, $section, $this->config(), $this->walker());
        self::assertCount(1, $pw->getSections());
    }

    public function testEmptyImageSrcIsSkipped(): void
    {
        $resolver = $this->createMock(ImageResolverInterface::class);
        $resolver->expects(self::never())->method('resolveToTempPath');

        $c   = new InlineComposer(new StyleMapper(), $resolver);
        $dom = new DOMDocument();
        $dom->loadHTML('<body><p><img src="" alt="x"/></p></body>');
        $p = $dom->getElementsByTagName('p')->item(0);
        self::assertInstanceOf(DOMElement::class, $p);

        $pw      = new PhpWord();
        $section = $pw->addSection();
        $c->compose($p, $section, $this->config(), $this->walker());
        self::assertCount(1, $pw->getSections());
    }

    public function testInlineImageEmbedsWithDimensions(): void
    {
        $tmp = tempnam(sys_get_temp_dir(), 'htw_inline_img_') . '.png';
        $png = base64_decode(self::PNG_1X1, true);
        self::assertNotFalse($png);
        file_put_contents($tmp, $png);

        try {
            $resolver = $this->createMock(ImageResolverInterface::class);
            $resolver->method('resolveToTempPath')->willReturn($tmp);

            $c   = new InlineComposer(new StyleMapper(), $resolver);
            $dom = new DOMDocument();
            $dom->loadHTML('<body><p><img src="data:image/png;base64,xx" width="40" height="20"/></p></body>');
            $p = $dom->getElementsByTagName('p')->item(0);
            self::assertInstanceOf(DOMElement::class, $p);

            $pw      = new PhpWord();
            $section = $pw->addSection();
            $c->compose($p, $section, $this->config(), $this->walker());
            self::assertNotEmpty($section->getElements());
        } finally {
            @unlink($tmp);
        }
    }

    public function testInlineImageResolveFailureAddsPlaceholder(): void
    {
        $resolver = $this->createMock(ImageResolverInterface::class);
        $resolver->method('resolveToTempPath')->willThrowException(new ImageResolveException('fail'));

        $c   = new InlineComposer(new StyleMapper(), $resolver);
        $dom = new DOMDocument();
        $dom->loadHTML('<body><p><img src="https://example.com/x.png"/></p></body>');
        $p = $dom->getElementsByTagName('p')->item(0);
        self::assertInstanceOf(DOMElement::class, $p);

        $pw      = new PhpWord();
        $section = $pw->addSection();
        $c->compose($p, $section, $this->config(), $this->walker());
        self::assertNotEmpty($section->getElements());
    }
}
