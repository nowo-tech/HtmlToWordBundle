<?php

declare(strict_types=1);

namespace Nowo\HtmlToWordBundle\Tests\Unit\Builder;

use DOMDocument;
use Nowo\HtmlToWordBundle\Builder\HeaderFooterBuilder;
use Nowo\HtmlToWordBundle\Builder\ImageResolverInterface;
use Nowo\HtmlToWordBundle\Builder\InlineComposer;
use Nowo\HtmlToWordBundle\Builder\SectionConfigurator;
use Nowo\HtmlToWordBundle\Builder\StyleMapper;
use Nowo\HtmlToWordBundle\Builder\WordDocumentBuilder;
use Nowo\HtmlToWordBundle\Config\ResolvedConfig;
use Nowo\HtmlToWordBundle\Parser\HtmlParser;
use Nowo\HtmlToWordBundle\Parser\HtmlSanitizer;
use Nowo\HtmlToWordBundle\Parser\RemoteHttpImageInliner;
use Nowo\HtmlToWordBundle\Transformer\TransformerChain;
use PhpOffice\PhpWord\PhpWord;
use PHPUnit\Framework\TestCase;

final class WordDocumentBuilderTest extends TestCase
{
    public function testDispatchIgnoresCommentNodes(): void
    {
        $builder = new WordDocumentBuilder(
            new HtmlSanitizer(),
            new RemoteHttpImageInliner(new HtmlParser(), $this->createMock(ImageResolverInterface::class)),
            new HtmlParser(),
            new SectionConfigurator(),
            new HeaderFooterBuilder(),
            new TransformerChain([]),
            new InlineComposer(new StyleMapper(), $this->createMock(ImageResolverInterface::class)),
        );

        $dom     = new DOMDocument();
        $comment = $dom->createComment('skip-me');
        $section = (new PhpWord())->addSection();

        $builder->dispatch($comment, $section, ResolvedConfig::fromArray(['strict_mode' => false]));
        self::assertSame([], $section->getElements());
    }

    public function testAppendRichTextDelegatesToInlineComposer(): void
    {
        $builder = new WordDocumentBuilder(
            new HtmlSanitizer(),
            new RemoteHttpImageInliner(new HtmlParser(), $this->createMock(ImageResolverInterface::class)),
            new HtmlParser(),
            new SectionConfigurator(),
            new HeaderFooterBuilder(),
            new TransformerChain([]),
            new InlineComposer(new StyleMapper(), $this->createMock(ImageResolverInterface::class)),
        );

        $dom = new DOMDocument();
        $dom->loadHTML('<body><p>Hi</p></body>');
        $p       = $dom->getElementsByTagName('p')->item(0);
        $section = (new PhpWord())->addSection();
        self::assertNotNull($p);

        $builder->appendRichText($p, $section, ResolvedConfig::fromArray([
            'fonts' => ['default' => 'Arial', 'default_size' => 11],
        ]));
        self::assertNotEmpty($section->getElements());
    }
}
