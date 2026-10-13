<?php

declare(strict_types=1);

namespace Nowo\HtmlToWordBundle\Tests\Unit\Export;

use Nowo\HtmlToWordBundle\Builder\ImageResolverInterface;
use Nowo\HtmlToWordBundle\Config\ResolvedConfig;
use Nowo\HtmlToWordBundle\Engine\PhpWordEngine;
use Nowo\HtmlToWordBundle\Export\DocxExporter;
use Nowo\HtmlToWordBundle\Model\WordDocument;
use Nowo\HtmlToWordBundle\Parser\HtmlParser;
use Nowo\HtmlToWordBundle\Parser\RemoteHttpImageInliner;
use PhpOffice\PhpWord\PhpWord;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class DocxExporterContentDispositionTest extends TestCase
{
    /**
     * @return iterable<string, array{string, string}>
     */
    public static function filenameProvider(): iterable
    {
        yield 'plain' => ['report.docx', 'attachment; filename=report.docx'];
        yield 'quote injection' => ['a".docx', "attachment; filename=a_.docx; filename*=utf-8''a%22.docx"];
        yield 'header injection' => ["x.docx\r\nSet-Cookie: a=b", 'attachment; filename="x.docx_Set-Cookie: a=b"'];
        yield 'path separators' => ['../../etc/x.docx', 'attachment; filename=.._.._etc_x.docx'];
        yield 'utf-8' => ['informe ñ.docx', "attachment; filename=\"informe _.docx\"; filename*=utf-8''informe%20%C3%B1.docx"];
        yield 'percent' => ['100%.docx', "attachment; filename=100_.docx; filename*=utf-8''100%25.docx"];
        yield 'empty' => ["\r\n", 'attachment; filename=document.docx'];
    }

    #[DataProvider('filenameProvider')]
    public function testStreamAndBinaryResponsesUseSafeDisposition(string $filename, string $expected): void
    {
        $exporter = new DocxExporter(new RemoteHttpImageInliner(new HtmlParser(), $this->createMock(ImageResolverInterface::class)));

        $stream = $exporter->toStreamResponse($this->document($filename));
        self::assertSame($expected, $stream->headers->get('Content-Disposition'));

        $binary = $exporter->toBinaryResponse($this->document($filename));
        try {
            self::assertSame($expected, $binary->headers->get('Content-Disposition'));
        } finally {
            @unlink($binary->getFile()->getPathname());
        }
    }

    private function document(string $filename): WordDocument
    {
        $phpWord = new PhpWord();
        $phpWord->addSection()->addText('x');

        return new WordDocument($phpWord, ResolvedConfig::fromArray(['export' => ['filename' => $filename]]), PhpWordEngine::NAME);
    }
}
