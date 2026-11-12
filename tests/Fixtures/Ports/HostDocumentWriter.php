<?php

declare(strict_types=1);

namespace Nowo\HtmlToWordBundle\Tests\Fixtures\Ports;

use Nowo\HtmlToWordBundle\Export\DocumentWriterInterface;
use Nowo\HtmlToWordBundle\Model\WordDocument;

use function fwrite;

/** Host writer on the 1.8 port only. */
final class HostDocumentWriter implements DocumentWriterInterface
{
    public const BYTES = 'host-writer';

    public function toBytes(WordDocument $document): string
    {
        return self::BYTES;
    }

    public function toStream(WordDocument $document, mixed $stream): void
    {
        fwrite($stream, self::BYTES);
    }

    public function toFile(WordDocument $document, string $path): void
    {
    }

    public function toFlysystem(WordDocument $document, string $remotePath): void
    {
    }
}
