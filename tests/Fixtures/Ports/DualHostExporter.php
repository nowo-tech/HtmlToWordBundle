<?php

declare(strict_types=1);

namespace Nowo\HtmlToWordBundle\Tests\Fixtures\Ports;

use Nowo\HtmlToWordBundle\Export\DocumentWriterInterface;
use Nowo\HtmlToWordBundle\Export\ExporterInterface;
use Nowo\HtmlToWordBundle\Model\WordDocument;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\StreamedResponse;

use function fwrite;

/** Host exporter on both ports (migration in progress). */
final class DualHostExporter implements DocumentWriterInterface, ExporterInterface
{
    public const BYTES = 'dual-host';

    public function toBytes(WordDocument $document): string
    {
        return self::BYTES;
    }

    public function toStream(WordDocument $document, mixed $stream): void
    {
        fwrite($stream, self::BYTES);
    }

    public function toStreamResponse(WordDocument $document): StreamedResponse
    {
        return new StreamedResponse();
    }

    public function toBinaryResponse(WordDocument $document): BinaryFileResponse
    {
        return new BinaryFileResponse(__FILE__);
    }

    public function toFile(WordDocument $document, string $path): void
    {
    }

    public function toFlysystem(WordDocument $document, string $remotePath): void
    {
    }
}
