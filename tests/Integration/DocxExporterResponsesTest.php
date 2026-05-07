<?php

declare(strict_types=1);

namespace Nowo\HtmlToWordBundle\Tests\Integration;

use League\Flysystem\FilesystemOperator;
use Nowo\HtmlToWordBundle\Config\ResolvedConfig;
use Nowo\HtmlToWordBundle\Converter\HtmlToWordConverter;
use Nowo\HtmlToWordBundle\Exception\ExportException;
use Nowo\HtmlToWordBundle\Export\DocxExporter;
use Nowo\HtmlToWordBundle\Model\WordDocument;
use Nowo\HtmlToWordBundle\Tests\Fixtures\AppKernel;
use PHPUnit\Framework\Assert;
use PHPUnit\Framework\Attributes\PreserveGlobalState;
use PHPUnit\Framework\Attributes\RunInSeparateProcess;
use stdClass;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

final class DocxExporterResponsesTest extends KernelTestCase
{
    protected static function getKernelClass(): string
    {
        return AppKernel::class;
    }

    protected function tearDown(): void
    {
        self::ensureKernelShutdown();
        parent::tearDown();
    }

    #[RunInSeparateProcess]
    #[PreserveGlobalState(false)]
    public function testToStreamResponseOutputsZipPayload(): void
    {
        self::bootKernel();
        /** @var HtmlToWordConverter $converter */
        $converter = self::getContainer()->get(HtmlToWordConverter::class);
        $exporter  = new DocxExporter();

        $doc      = $converter->convert('<p>stream</p>');
        $response = $exporter->toStreamResponse($doc);

        ob_start();
        $response->sendContent();
        $body = (string) ob_get_clean();

        self::assertStringStartsWith('PK', $body);
    }

    #[RunInSeparateProcess]
    #[PreserveGlobalState(false)]
    public function testToBinaryResponseUsesTempFile(): void
    {
        self::bootKernel();
        /** @var HtmlToWordConverter $converter */
        $converter = self::getContainer()->get(HtmlToWordConverter::class);
        $exporter  = new DocxExporter();

        $doc      = $converter->convert('<p>binary</p>');
        $response = $exporter->toBinaryResponse($doc);
        $path     = $response->getFile()->getPathname();
        self::assertFileExists($path);
        self::assertGreaterThan(1000, filesize($path) ?: 0);
    }

    #[RunInSeparateProcess]
    #[PreserveGlobalState(false)]
    public function testToFileWritesDocx(): void
    {
        self::bootKernel();
        /** @var HtmlToWordConverter $converter */
        $converter = self::getContainer()->get(HtmlToWordConverter::class);
        $exporter  = new DocxExporter();

        $tmp = sys_get_temp_dir() . '/htw_export_' . uniqid() . '.docx';
        try {
            $exporter->toFile($converter->convert('<p>file</p>'), $tmp);
            self::assertGreaterThan(1000, filesize($tmp) ?: 0);
        } finally {
            @unlink($tmp);
        }
    }

    #[RunInSeparateProcess]
    #[PreserveGlobalState(false)]
    public function testWrongEngineThrowsOnExport(): void
    {
        self::bootKernel();

        $exporter = new DocxExporter();

        $bad = new WordDocument(new stdClass(), ResolvedConfig::fromArray([
            'strict_mode' => false,
            'export'      => ['filename' => 'x.docx'],
        ]), 'not-phpword');

        $this->expectException(ExportException::class);
        $exporter->toFile($bad, sys_get_temp_dir() . '/nope.docx');
    }

    #[RunInSeparateProcess]
    #[PreserveGlobalState(false)]
    public function testToFlysystemWritesThroughOperator(): void
    {
        self::bootKernel();

        /** @var HtmlToWordConverter $converter */
        $converter = self::getContainer()->get(HtmlToWordConverter::class);

        $fs = $this->createMock(FilesystemOperator::class);
        $fs->expects(self::once())->method('writeStream')->willReturnCallback(
            static function (string $path, $contents): void {
                Assert::assertSame('remote/out.docx', $path);
                Assert::assertIsResource($contents);
                fread($contents, 4096);
            },
        );

        $exporter = new DocxExporter($fs);
        $exporter->toFlysystem($converter->convert('<p>fly</p>'), 'remote/out.docx');
    }
}
