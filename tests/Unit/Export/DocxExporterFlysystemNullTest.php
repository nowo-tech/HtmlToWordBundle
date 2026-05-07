<?php

declare(strict_types=1);

namespace Nowo\HtmlToWordBundle\Tests\Unit\Export;

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

final class DocxExporterFlysystemNullTest extends TestCase
{
    public function testToFlysystemWithoutAdapterThrows(): void
    {
        $doc = new WordDocument(new PhpWord(), ResolvedConfig::fromArray([
            'strict_mode' => false,
            'export'      => ['filename' => 'x.docx'],
        ]), PhpWordEngine::NAME);

        $inliner  = new RemoteHttpImageInliner(new HtmlParser(), $this->createMock(ImageResolverInterface::class));
        $exporter = new DocxExporter($inliner);

        $this->expectException(ExportException::class);
        $this->expectExceptionMessage('Flysystem adapter');
        $exporter->toFlysystem($doc, 'any/path.docx');
    }
}
