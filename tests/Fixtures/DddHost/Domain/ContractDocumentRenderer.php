<?php

declare(strict_types=1);

namespace Nowo\HtmlToWordBundle\Tests\Fixtures\DddHost\Domain;

/** Host domain port: plain PHP, no bundle or framework type. */
interface ContractDocumentRenderer
{
    /** Returns the rendered contract as DOCX bytes. */
    public function render(string $contractId, string $html): string;
}
