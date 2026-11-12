<?php

declare(strict_types=1);

namespace Nowo\HtmlToWordBundle\Export;

use Nowo\HtmlToWordBundle\Exception\ExportException;
use Nowo\HtmlToWordBundle\Http\DocxResponseFactory;
use Nowo\HtmlToWordBundle\Model\WordDocument;

/**
 * Framework-free output port for a converted {@see WordDocument} (REQ-ARCH-001 rule 3): bytes, a stream, a local path
 * or the configured Flysystem adapter. No Symfony or HTTP type in its signatures, so a CLI command, a Messenger
 * handler or a DDD / hexagonal adapter can depend on it without HttpFoundation.
 *
 * Every method writes the document once and then deletes its temporary images (the document cannot be written again
 * after that: convert it again). HTTP downloads are built at the edge by {@see DocxResponseFactory}.
 *
 * Since 1.8.0; replaces the deprecated {@see ExporterInterface} (removed in 2.0).
 *
 * @author Héctor Franco Aceituno <hectorfranco@nowo.tech>
 */
interface DocumentWriterInterface
{
    /**
     * Returns the DOCX as a binary string.
     *
     * @throws ExportException
     */
    public function toBytes(WordDocument $document): string;

    /**
     * Writes the DOCX into a writable stream the caller opened (and closes). The stream is not rewound.
     *
     * @param resource $stream
     *
     * @throws ExportException
     */
    public function toStream(WordDocument $document, mixed $stream): void;

    /**
     * Writes the DOCX to a local path.
     *
     * @throws ExportException
     */
    public function toFile(WordDocument $document, string $path): void;

    /**
     * Writes into the profile's {@code export.flysystem_adapter} (or the injected default operator).
     *
     * @throws ExportException
     */
    public function toFlysystem(WordDocument $document, string $remotePath): void;
}
