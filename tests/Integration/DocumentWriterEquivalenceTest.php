<?php

declare(strict_types=1);

namespace Nowo\HtmlToWordBundle\Tests\Integration;

use Nowo\HtmlToWordBundle\Config\ResolvedConfig;
use Nowo\HtmlToWordBundle\Converter\HtmlToWordConverter;
use Nowo\HtmlToWordBundle\Exception\ExportException;
use Nowo\HtmlToWordBundle\Export\DocumentWriterInterface;
use Nowo\HtmlToWordBundle\Export\DocxExporter;
use Nowo\HtmlToWordBundle\Export\ExporterInterface;
use Nowo\HtmlToWordBundle\Http\DocxResponseFactory;
use Nowo\HtmlToWordBundle\Model\WordDocument;
use Nowo\HtmlToWordBundle\Parser\RemoteHttpImageInliner;
use Nowo\HtmlToWordBundle\Tests\Fixtures\AppKernel;
use PHPUnit\Framework\Attributes\IgnoreDeprecations;
use stdClass;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\HttpFoundation\Response;
use ZipArchive;

use function fclose;
use function file_get_contents;
use function file_put_contents;
use function fopen;
use function glob;
use function ksort;
use function ob_get_clean;
use function ob_start;
use function preg_replace;
use function rewind;
use function stream_get_contents;
use function sys_get_temp_dir;
use function uniqid;
use function unlink;

/**
 * 1.8.0 equivalence: the framework-free {@see DocumentWriterInterface} methods and {@see DocxResponseFactory} produce
 * the same DOCX parts and the same download headers as the deprecated {@see ExporterInterface}
 * methods, and release the document's temporary images the same way.
 *
 * DOCX parts are compared entry by entry; `docProps/core.xml` is skipped because PHPWord stamps the creation time,
 * and the random list ids (`w:nsid`, `w:tmpl`) PHPWord generates per write are masked in `word/numbering.xml`.
 */
final class DocumentWriterEquivalenceTest extends KernelTestCase
{
    private const PNG_1X1 = 'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mP8z8BQDwAEhQGAhKmMIQAAAABJRU5ErkJggg==';

    private const HTML = '<h1>Report ñ</h1><p>Body <strong>bold</strong></p><ul><li>a</li><li>b</li></ul>'
        . '<table><tr><td>x</td><td>y</td></tr></table><img src="data:image/png;base64,' . self::PNG_1X1 . '"/>';

    /** @var list<string> */
    private array $cleanup = [];

    protected static function getKernelClass(): string
    {
        return AppKernel::class;
    }

    protected function tearDown(): void
    {
        foreach ($this->cleanup as $path) {
            @unlink($path);
        }
        self::ensureKernelShutdown();
        parent::tearDown();
    }

    public function testBytesAndStreamMatchFile(): void
    {
        [$converter, $writer] = $this->services();

        $reference = $this->parts($this->viaFile($writer, $converter->convert(self::HTML)));

        self::assertSame($reference, $this->parts($writer->toBytes($converter->convert(self::HTML))));

        $stream = fopen('php://temp', 'w+');
        self::assertIsResource($stream);
        $writer->toStream($converter->convert(self::HTML), $stream);
        rewind($stream);
        self::assertSame($reference, $this->parts((string) stream_get_contents($stream)));
        fclose($stream);
    }

    #[IgnoreDeprecations]
    public function testStreamedResponseMatchesDeprecatedStreamResponse(): void
    {
        [$converter, $writer, $factory] = $this->services();

        $old = $writer->toStreamResponse($converter->convert(self::HTML));
        $new = $factory->streamed($converter->convert(self::HTML));

        self::assertSame($this->headers($old), $this->headers($new));
        self::assertSame($this->parts($this->send($old)), $this->parts($this->send($new)));
    }

    #[IgnoreDeprecations]
    public function testBinaryFileMatchesDeprecatedBinaryResponse(): void
    {
        [$converter, $writer, $factory] = $this->services();

        $old             = $writer->toBinaryResponse($converter->convert(self::HTML));
        $new             = $factory->binaryFile($converter->convert(self::HTML));
        $this->cleanup[] = $old->getFile()->getPathname();
        $this->cleanup[] = $new->getFile()->getPathname();

        self::assertSame($this->headers($old), $this->headers($new));
        self::assertSame(
            $this->parts((string) file_get_contents($old->getFile()->getPathname())),
            $this->parts((string) file_get_contents($new->getFile()->getPathname())),
        );
    }

    public function testEveryWriterMethodReleasesTemporaryImages(): void
    {
        [$converter, $writer, $factory] = $this->services();

        $runs = [
            'toBytes'  => $writer->toBytes(...),
            'toStream' => static function (WordDocument $d) use ($writer): void {
                $stream = fopen('php://memory', 'w+');
                self::assertIsResource($stream);
                $writer->toStream($d, $stream);
                fclose($stream);
            },
            'binaryFile' => function (WordDocument $d) use ($factory): void {
                $this->cleanup[] = $factory->binaryFile($d)->getFile()->getPathname();
            },
            'streamed' => function (WordDocument $d) use ($factory): void {
                $this->send($factory->streamed($d));
            },
        ];

        foreach ($runs as $name => $run) {
            $document = $converter->convert(self::HTML);
            self::assertCount(1, $document->temporaryFiles(), $name);
            self::assertFileExists($document->temporaryFiles()[0], $name);
            $run($document);
            self::assertFileDoesNotExist($document->temporaryFiles()[0], $name);
        }
    }

    public function testToStreamRejectsNonStream(): void
    {
        [$converter, $writer] = $this->services();
        $document             = $converter->convert(self::HTML);

        try {
            $writer->toStream($document, 'not-a-stream'); // @phpstan-ignore argument.type (the contract violation under test)
            self::fail('Expected ExportException.');
        } catch (ExportException $e) {
            self::assertStringContainsString('stream resource', $e->getMessage());
        }
        self::assertFileDoesNotExist($document->temporaryFiles()[0]);
    }

    public function testToStreamFailsOnReadOnlyStream(): void
    {
        [$converter, $writer] = $this->services();
        $path                 = sys_get_temp_dir() . '/htw_ro_' . uniqid('', true);
        file_put_contents($path, '');
        $this->cleanup[] = $path;
        $readOnly        = fopen($path, 'r');
        self::assertIsResource($readOnly);

        try {
            $this->expectException(ExportException::class);
            $writer->toStream($converter->convert('<p>ro</p>'), $readOnly);
        } finally {
            fclose($readOnly);
        }
    }

    public function testWrongEngineFailsTheSameWayAndLeavesNoTemporaryDocx(): void
    {
        [, $writer, $factory] = $this->services();
        $before               = glob(sys_get_temp_dir() . '/html_to_word_*') ?: [];

        foreach ([
            $writer->toBytes(...),
            $factory->binaryFile(...),
        ] as $run) {
            try {
                $run($this->foreignEngineDocument());
                self::fail('Expected ExportException.');
            } catch (ExportException $e) {
                self::assertStringContainsString('only supports engine', $e->getMessage());
            }
        }

        self::assertSame($before, glob(sys_get_temp_dir() . '/html_to_word_*') ?: []);
    }

    /** Audit MW-W-L1: a missing Flysystem adapter leaves no temp DOCX and releases the document's temp images. */
    public function testMissingFlysystemAdapterCleansUp(): void
    {
        [$converter, $writer] = $this->services();
        $document             = $converter->convert(self::HTML);
        $before               = glob(sys_get_temp_dir() . '/html_to_word_fs_*') ?: [];
        self::assertFileExists($document->temporaryFiles()[0]);

        try {
            $writer->toFlysystem($document, 'remote/x.docx');
            self::fail('Expected ExportException.');
        } catch (ExportException $e) {
            self::assertStringContainsString('Flysystem adapter is not configured', $e->getMessage());
        }

        self::assertFileDoesNotExist($document->temporaryFiles()[0]);
        self::assertSame($before, glob(sys_get_temp_dir() . '/html_to_word_fs_*') ?: []);
    }

    public function testFactoryWritesThroughAnyDocumentWriter(): void
    {
        $writer = new class implements DocumentWriterInterface {
            public function toBytes(WordDocument $document): string
            {
                return 'bytes';
            }

            public function toStream(WordDocument $document, mixed $stream): void
            {
                fwrite($stream, 'streamed-by-host');
            }

            public function toFile(WordDocument $document, string $path): void
            {
                file_put_contents($path, 'file-by-host');
            }

            public function toFlysystem(WordDocument $document, string $remotePath): void
            {
            }
        };
        $factory  = new DocxResponseFactory($writer);
        $document = $this->foreignEngineDocument();

        self::assertSame('streamed-by-host', $this->send($factory->streamed($document)));
        $binary          = $factory->binaryFile($document);
        $this->cleanup[] = $binary->getFile()->getPathname();
        self::assertSame('file-by-host', file_get_contents($binary->getFile()->getPathname()));
        self::assertSame('attachment; filename=x.docx', $binary->headers->get('Content-Disposition'));
    }

    /**
     * @return array{HtmlToWordConverter, DocxExporter, DocxResponseFactory}
     */
    private function services(): array
    {
        self::bootKernel();
        $container = self::getContainer();

        $converter = $container->get(HtmlToWordConverter::class);
        self::assertInstanceOf(HtmlToWordConverter::class, $converter);
        $writer = $container->get(DocumentWriterInterface::class);
        self::assertInstanceOf(DocxExporter::class, $writer);
        $factory = $container->get(DocxResponseFactory::class);
        self::assertInstanceOf(DocxResponseFactory::class, $factory);
        self::assertInstanceOf(RemoteHttpImageInliner::class, $container->get(RemoteHttpImageInliner::class));

        return [$converter, $writer, $factory];
    }

    private function viaFile(DocxExporter $writer, WordDocument $document): string
    {
        $path            = sys_get_temp_dir() . '/htw_eq_' . uniqid('', true) . '.docx';
        $this->cleanup[] = $path;
        $writer->toFile($document, $path);

        return (string) file_get_contents($path);
    }

    private function send(Response $response): string
    {
        ob_start();
        $response->sendContent();

        return (string) ob_get_clean();
    }

    /**
     * @return array<string, string|null>
     */
    private function headers(Response $response): array
    {
        return [
            'status'              => (string) $response->getStatusCode(),
            'Content-Type'        => $response->headers->get('Content-Type'),
            'Content-Disposition' => $response->headers->get('Content-Disposition'),
        ];
    }

    /**
     * @return array<string, string>
     */
    private function parts(string $docx): array
    {
        self::assertStringStartsWith('PK', $docx);
        $path            = sys_get_temp_dir() . '/htw_parts_' . uniqid('', true) . '.docx';
        $this->cleanup[] = $path;
        file_put_contents($path, $docx);

        $zip = new ZipArchive();
        self::assertTrue($zip->open($path));
        $parts = [];
        for ($i = 0; $i < $zip->numFiles; ++$i) {
            $name = (string) $zip->getNameIndex($i);
            if ($name !== 'docProps/core.xml') {
                $parts[$name] = (string) preg_replace('/<w:(nsid|tmpl) w:val="[0-9A-Fa-f]+"\s*\/>/', '<w:$1/>', (string) $zip->getFromIndex($i));
            }
        }
        $zip->close();
        ksort($parts);

        return $parts;
    }

    private function foreignEngineDocument(): WordDocument
    {
        return new WordDocument(new stdClass(), ResolvedConfig::fromArray([
            'strict_mode' => false,
            'export'      => ['filename' => 'x.docx'],
        ]), 'not-phpword');
    }
}
