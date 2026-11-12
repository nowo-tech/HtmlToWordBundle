<?php

declare(strict_types=1);

namespace Nowo\HtmlToWordBundle\Tests\Fixtures\DddHost\Infrastructure;

use Nowo\HtmlToWordBundle\Converter\HtmlToWordConverterInterface;
use Nowo\HtmlToWordBundle\Export\DocumentWriterInterface;
use Nowo\HtmlToWordBundle\Tests\Fixtures\DddHost\Domain\ContractDocumentRenderer;

/** Host infrastructure adapter: implements the domain port with the bundle's framework-free ports only. */
final readonly class HtmlToWordContractRenderer implements ContractDocumentRenderer
{
    public function __construct(
        private HtmlToWordConverterInterface $converter,
        private DocumentWriterInterface $writer,
    ) {
    }

    public function render(string $contractId, string $html): string
    {
        $document = $this->converter->convertWithOptions($html, ['export' => ['filename' => $contractId . '.docx']]);

        return $this->writer->toBytes($document);
    }
}
