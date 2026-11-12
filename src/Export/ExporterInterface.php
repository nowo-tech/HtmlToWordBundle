<?php

declare(strict_types=1);

namespace Nowo\HtmlToWordBundle\Export;

use Nowo\HtmlToWordBundle\Http\DocxResponseFactory;
use Nowo\HtmlToWordBundle\Model\WordDocument;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Symfony / filesystem export helpers for {@see WordDocument}.
 *
 * @deprecated since 1.8.0, removed in 2.0. It mixes HttpFoundation responses with storage (REQ-ARCH-001 rule 3):
 *             write documents through {@see DocumentWriterInterface} and build downloads with
 *             {@see DocxResponseFactory}. Hosts implementing it implement
 *             {@see DocumentWriterInterface} instead.
 *
 * @author Héctor Franco Aceituno <hectorfranco@nowo.tech>
 */
interface ExporterInterface
{
    /**
     * @deprecated since 1.8.0, use {@see DocxResponseFactory::streamed()}
     */
    public function toStreamResponse(WordDocument $document): StreamedResponse;

    /**
     * @deprecated since 1.8.0, use {@see DocxResponseFactory::binaryFile()}
     */
    public function toBinaryResponse(WordDocument $document): BinaryFileResponse;

    public function toFile(WordDocument $document, string $path): void;

    /**
     * Writes into the configured Flysystem adapter (if injected).
     */
    public function toFlysystem(WordDocument $document, string $remotePath): void;
}
