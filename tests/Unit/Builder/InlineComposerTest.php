<?php

declare(strict_types=1);

namespace Nowo\HtmlToWordBundle\Tests\Unit\Builder;

use DOMDocument;
use DOMElement;
use DOMNode;
use Nowo\HtmlToWordBundle\Builder\ImageResolver;
use Nowo\HtmlToWordBundle\Builder\InlineComposer;
use Nowo\HtmlToWordBundle\Builder\StyleMapper;
use Nowo\HtmlToWordBundle\Config\ResolvedConfig;
use Nowo\HtmlToWordBundle\Transformer\DocumentWalkerInterface;
use PhpOffice\PhpWord\PhpWord;
use PHPUnit\Framework\TestCase;

final class InlineComposerTest extends TestCase
{
    private function walker(): DocumentWalkerInterface
    {
        return new class implements DocumentWalkerInterface {
            public function dispatch(DOMNode $node, \PhpOffice\PhpWord\Element\AbstractContainer $container, ResolvedConfig $config): void
            {
            }

            public function appendRichText(DOMNode $node, \PhpOffice\PhpWord\Element\AbstractContainer $container, ResolvedConfig $config): void
            {
            }
        };
    }

    private function config(): ResolvedConfig
    {
        return ResolvedConfig::fromArray([
            'strict_mode' => false,
            'fonts'       => ['default' => 'Arial', 'default_size' => 11],
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
}
