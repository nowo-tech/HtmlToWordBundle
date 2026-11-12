<?php

declare(strict_types=1);

namespace Nowo\HtmlToWordBundle\Tests\Fixtures\Ports;

use Nowo\HtmlToWordBundle\Export\DocumentWriterInterface;
use Nowo\HtmlToWordBundle\Model\WordDocument;

/** Host decorator of the 1.8 port (e.g. audit logging around the bundle writer). */
final readonly class DecoratingDocumentWriter implements DocumentWriterInterface
{
    public function __construct(
        public DocumentWriterInterface $inner,
    ) {
    }

    public function toBytes(WordDocument $document): string
    {
        return $this->inner->toBytes($document);
    }

    public function toStream(WordDocument $document, mixed $stream): void
    {
        $this->inner->toStream($document, $stream);
    }

    public function toFile(WordDocument $document, string $path): void
    {
        $this->inner->toFile($document, $path);
    }

    public function toFlysystem(WordDocument $document, string $remotePath): void
    {
        $this->inner->toFlysystem($document, $remotePath);
    }
}
