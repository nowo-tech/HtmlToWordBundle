<?php

declare(strict_types=1);

namespace Nowo\HtmlToWordBundle\Tests\Unit\Export;

use League\Flysystem\FilesystemOperator;
use Nowo\HtmlToWordBundle\Builder\ImageResolverInterface;
use Nowo\HtmlToWordBundle\Config\ResolvedConfig;
use Nowo\HtmlToWordBundle\Engine\PhpWordEngine;
use Nowo\HtmlToWordBundle\Exception\ExportException;
use Nowo\HtmlToWordBundle\Export\DocxExporter;
use Nowo\HtmlToWordBundle\Model\WordDocument;
use Nowo\HtmlToWordBundle\Parser\HtmlParser;
use Nowo\HtmlToWordBundle\Parser\RemoteHttpImageInliner;
use PhpOffice\PhpWord\PhpWord;
use PHPUnit\Framework\TestCase;
use stdClass;
use Symfony\Component\DependencyInjection\ServiceLocator;

final class DocxExporterFlysystemNullTest extends TestCase
{
    public function testToFlysystemWithoutAdapterThrows(): void
    {
        $exporter = new DocxExporter($this->inliner());

        $this->expectException(ExportException::class);
        $this->expectExceptionMessage('Flysystem adapter');
        $exporter->toFlysystem($this->document(null), 'any/path.docx');
    }

    public function testProfileAdapterIsResolvedFromLocator(): void
    {
        $fallback = $this->createMock(FilesystemOperator::class);
        $fallback->expects(self::never())->method('writeStream');
        $profileFs = $this->createMock(FilesystemOperator::class);
        $profileFs->expects(self::once())->method('writeStream')->with('remote/a.docx', self::isType('resource'));

        $exporter = new DocxExporter($this->inliner(), $fallback, new ServiceLocator([
            'app.storage' => static fn (): FilesystemOperator => $profileFs,
        ]));

        $exporter->toFlysystem($this->document(' app.storage '), 'remote/a.docx');
    }

    public function testEmptyProfileAdapterFallsBackToInjectedOperator(): void
    {
        $fallback = $this->createMock(FilesystemOperator::class);
        $fallback->expects(self::once())->method('writeStream');

        (new DocxExporter($this->inliner(), $fallback))->toFlysystem($this->document(''), 'remote/b.docx');
    }

    public function testUnknownProfileAdapterThrows(): void
    {
        $exporter = new DocxExporter($this->inliner(), null, new ServiceLocator([]));

        $this->expectException(ExportException::class);
        $this->expectExceptionMessage('Flysystem adapter "app.missing" is not available');
        $exporter->toFlysystem($this->document('app.missing'), 'remote/c.docx');
    }

    public function testProfileAdapterWithoutLocatorThrows(): void
    {
        $this->expectException(ExportException::class);
        $this->expectExceptionMessage('is not available');
        (new DocxExporter($this->inliner()))->toFlysystem($this->document('app.storage'), 'remote/d.docx');
    }

    public function testProfileAdapterOfWrongTypeThrows(): void
    {
        $exporter = new DocxExporter($this->inliner(), null, new ServiceLocator([
            'app.storage' => static fn (): stdClass => new stdClass(),
        ]));

        $this->expectException(ExportException::class);
        $this->expectExceptionMessage('must implement League\Flysystem\FilesystemOperator');
        $exporter->toFlysystem($this->document('app.storage'), 'remote/e.docx');
    }

    private function inliner(): RemoteHttpImageInliner
    {
        return new RemoteHttpImageInliner(new HtmlParser(), $this->createMock(ImageResolverInterface::class));
    }

    private function document(?string $adapter): WordDocument
    {
        $phpWord = new PhpWord();
        $phpWord->addSection()->addText('fly');

        return new WordDocument($phpWord, ResolvedConfig::fromArray([
            'strict_mode' => false,
            'export'      => ['filename' => 'x.docx', 'flysystem_adapter' => $adapter],
        ]), PhpWordEngine::NAME);
    }
}
