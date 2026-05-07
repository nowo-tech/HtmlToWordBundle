<?php

declare(strict_types=1);

namespace Nowo\HtmlToWordBundle\Export;

use Nowo\HtmlToWordBundle\Model\WordDocument;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Symfony / filesystem export helpers for {@see WordDocument}.
 *
 * @author Héctor Franco Aceituno <hectorfranco@nowo.tech>
 */
interface ExporterInterface
{
    public function toStreamResponse(WordDocument $document): StreamedResponse;

    public function toBinaryResponse(WordDocument $document): BinaryFileResponse;

    public function toFile(WordDocument $document, string $path): void;

    /**
     * Writes into the configured Flysystem adapter (if injected).
     */
    public function toFlysystem(WordDocument $document, string $remotePath): void;
}
