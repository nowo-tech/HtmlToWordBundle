<?php

declare(strict_types=1);

namespace Nowo\HtmlToWordBundle\Tests\Unit\Model;

use LogicException;
use Nowo\HtmlToWordBundle\Config\ResolvedConfig;
use Nowo\HtmlToWordBundle\Model\WordDocument;
use PhpOffice\PhpWord\PhpWord;
use PHPUnit\Framework\TestCase;
use stdClass;

final class WordDocumentTest extends TestCase
{
    public function testPhpWordAccessor(): void
    {
        $pw = new PhpWord();
        $c  = ResolvedConfig::fromArray([
            'strict_mode' => false,
            'export'      => ['filename' => 'out.docx'],
        ]);
        $doc = new WordDocument($pw, $c, 'phpword');

        self::assertSame($pw, $doc->phpWord());
        self::assertSame($pw, $doc->native());
        self::assertSame('out.docx', $doc->suggestedFilename());
        self::assertSame('phpword', $doc->engine());
        self::assertArrayHasKey('strict_mode', $doc->resolvedConfig()->all());
    }

    public function testPhpWordThrowsForWrongEngine(): void
    {
        $doc = new WordDocument(new stdClass(), ResolvedConfig::fromArray(['export' => ['filename' => 'a.docx']]), 'other');

        $this->expectException(LogicException::class);
        $doc->phpWord();
    }
}
