<?php

declare(strict_types=1);

namespace Nowo\HtmlToWordBundle\Tests\Fixtures\Ports;

use Nowo\HtmlToWordBundle\Export\ExporterInterface;
use Nowo\HtmlToWordBundle\Model\WordDocument;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\StreamedResponse;

/** Host exporter still on the deprecated port only. */
final class LegacyHostExporter implements ExporterInterface
{
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
