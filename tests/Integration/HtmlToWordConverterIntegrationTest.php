<?php

declare(strict_types=1);

namespace Nowo\HtmlToWordBundle\Tests\Integration;

use Nowo\HtmlToWordBundle\Converter\HtmlToWordConverter;
use Nowo\HtmlToWordBundle\Exception\InvalidProfileException;
use Nowo\HtmlToWordBundle\Tests\Fixtures\AppKernel;
use PhpOffice\PhpWord\IOFactory;
use PHPUnit\Framework\Attributes\PreserveGlobalState;
use PHPUnit\Framework\Attributes\RunInSeparateProcess;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

final class HtmlToWordConverterIntegrationTest extends KernelTestCase
{
    protected static function getKernelClass(): string
    {
        return AppKernel::class;
    }

    protected function tearDown(): void
    {
        self::ensureKernelShutdown();
        parent::tearDown();
    }

    #[RunInSeparateProcess]
    #[PreserveGlobalState(false)]
    public function testConvertProducesWordDocument(): void
    {
        self::bootKernel();
        $converter = self::getContainer()->get(HtmlToWordConverter::class);
        self::assertInstanceOf(HtmlToWordConverter::class, $converter);

        $doc = $converter->convert('<p>Integration <strong>test</strong></p>');
        $tmp = sys_get_temp_dir() . '/htw_integration_' . uniqid() . '.docx';

        try {
            $writer = IOFactory::createWriter($doc->phpWord(), 'Word2007');
            $writer->save($tmp);
            self::assertFileExists($tmp);
            self::assertGreaterThan(2000, filesize($tmp) ?: 0);
            self::assertStringStartsWith('PK', (string) file_get_contents($tmp, false, null, 0, 4));
        } finally {
            @unlink($tmp);
        }
    }

    #[RunInSeparateProcess]
    #[PreserveGlobalState(false)]
    public function testUnknownProfileThrows(): void
    {
        self::bootKernel();
        $converter = self::getContainer()->get(HtmlToWordConverter::class);
        self::assertInstanceOf(HtmlToWordConverter::class, $converter);

        $this->expectException(InvalidProfileException::class);
        $converter->convertWithProfile('<p>x</p>', 'does_not_exist');
    }
}
