<?php

declare(strict_types=1);

namespace Nowo\HtmlToWordBundle\Tests\Unit\Builder;

use Nowo\HtmlToWordBundle\Builder\HeaderFooterBuilder;
use Nowo\HtmlToWordBundle\Builder\ImageResolver;
use Nowo\HtmlToWordBundle\Builder\InlineComposer;
use Nowo\HtmlToWordBundle\Builder\SectionConfigurator;
use Nowo\HtmlToWordBundle\Builder\StyleMapper;
use Nowo\HtmlToWordBundle\Builder\TemporaryImageFiles;
use Nowo\HtmlToWordBundle\Builder\WordDocumentBuilder;
use Nowo\HtmlToWordBundle\Config\ResolvedConfig;
use Nowo\HtmlToWordBundle\Exception\UnsupportedElementException;
use Nowo\HtmlToWordBundle\Parser\HtmlParser;
use Nowo\HtmlToWordBundle\Parser\HtmlSanitizer;
use Nowo\HtmlToWordBundle\Parser\RemoteHttpImageInliner;
use Nowo\HtmlToWordBundle\Transformer\ImageBlockTransformer;
use Nowo\HtmlToWordBundle\Transformer\TransformerChain;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpKernel\KernelEvents;

final class TemporaryImageFilesTest extends TestCase
{
    private const PNG_1X1 = 'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mP8z8BQDwAEhQGAhKmMIQAAAABJRU5ErkJggg==';

    public function testSubscribesToKernelTerminate(): void
    {
        self::assertArrayHasKey(KernelEvents::TERMINATE, TemporaryImageFiles::getSubscribedEvents());
    }

    public function testCollectionsReleaseOnlyTheirOwnFiles(): void
    {
        $tracker = new TemporaryImageFiles();
        $outside = $this->tempFile();
        $tracker->register($outside);
        $tracker->register('');

        $tracker->beginCollection();
        $a = $this->tempFile();
        $tracker->register($a);
        $tracker->beginCollection();
        $b = $this->tempFile();
        $tracker->register($b);
        self::assertSame([$b], $tracker->endCollection());
        self::assertSame([$a], $tracker->endCollection());
        self::assertSame([], $tracker->endCollection());

        $tracker->release([$a, '']);
        self::assertFileDoesNotExist($a);
        self::assertFileExists($b);
        self::assertSame([$outside, $b], $tracker->pending());

        $tracker->reset();
        self::assertFileDoesNotExist($b);
        self::assertFileDoesNotExist($outside);
        self::assertSame([], $tracker->pending());
    }

    public function testReleaseRemovesPathFromOpenCollection(): void
    {
        $tracker = new TemporaryImageFiles();
        $tracker->beginCollection();
        $a = $this->tempFile();
        $tracker->register($a);
        $tracker->release([$a]);

        self::assertSame([], $tracker->endCollection());
    }

    public function testImageResolverRegistersDataUriFiles(): void
    {
        $tracker = new TemporaryImageFiles();
        $path    = (new ImageResolver($tracker))->resolveToTempPath(
            'data:image/png;base64,' . self::PNG_1X1,
            ResolvedConfig::fromArray([]),
        );

        self::assertSame([$path], $tracker->pending());
        $tracker->releaseAll();
        self::assertFileDoesNotExist($path);
    }

    public function testBuilderAttachesFilesToDocumentAndReleasesThemOnFailure(): void
    {
        $tracker  = new TemporaryImageFiles();
        $resolver = new ImageResolver($tracker);
        $styles   = new StyleMapper();
        $builder  = new WordDocumentBuilder(
            new HtmlSanitizer(),
            new RemoteHttpImageInliner(new HtmlParser(), $resolver, $tracker),
            new HtmlParser(),
            new SectionConfigurator(),
            new HeaderFooterBuilder(),
            new TransformerChain([new ImageBlockTransformer($resolver, $styles)]),
            new InlineComposer($styles, $resolver),
        );
        $img = '<img src="data:image/png;base64,' . self::PNG_1X1 . '"/>';

        $doc = $builder->build($img, ResolvedConfig::fromArray(['strict_mode' => false]));
        self::assertCount(1, $doc->temporaryFiles());
        self::assertSame($doc->temporaryFiles(), $tracker->pending());

        try {
            $builder->build($img . '<unknown-tag>x</unknown-tag>', ResolvedConfig::fromArray(['strict_mode' => true]));
            self::fail('Expected strict mode failure');
        } catch (UnsupportedElementException) {
        }

        // Only the failed conversion's image was removed.
        self::assertSame($doc->temporaryFiles(), $tracker->pending());
        self::assertFileExists($doc->temporaryFiles()[0]);
        $tracker->releaseAll();
    }

    private function tempFile(): string
    {
        $path = tempnam(sys_get_temp_dir(), 'htw_track_');
        self::assertNotFalse($path);

        return $path;
    }
}
