<?php

declare(strict_types=1);

namespace Nowo\HtmlToWordBundle\Tests\Integration;

use Nowo\HtmlToWordBundle\Converter\HtmlToWordConverter;
use Nowo\HtmlToWordBundle\Tests\Fixtures\AppKernel;
use PHPUnit\Framework\Attributes\PreserveGlobalState;
use PHPUnit\Framework\Attributes\RunInSeparateProcess;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

final class HeaderFooterConversionTest extends KernelTestCase
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
    public function testHeaderFooterWithLogo(): void
    {
        self::bootKernel();
        /** @var HtmlToWordConverter $converter */
        $converter = self::getContainer()->get(HtmlToWordConverter::class);

        $png = base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mP8z8BQDwAEhQGAhKmMIQAAAABJRU5ErkJggg==', true);
        self::assertNotFalse($png);

        $logo = sys_get_temp_dir() . '/htw_logo_' . uniqid() . '.png';
        file_put_contents($logo, $png);

        try {
            $doc = $converter->convertWithOptions(
                '<p>With header and footer</p>',
                [
                    'header' => [
                        'enabled'    => true,
                        'logo'       => $logo,
                        'logo_width' => 40,
                        'text'       => 'Hdr',
                    ],
                    'footer' => [
                        'enabled'          => true,
                        'text'             => 'Ftr',
                        'show_page_number' => true,
                    ],
                ],
            );

            $tmp = sys_get_temp_dir() . '/htw_hf_' . uniqid() . '.docx';
            try {
                $writer = \PhpOffice\PhpWord\IOFactory::createWriter($doc->phpWord(), 'Word2007');
                $writer->save($tmp);
                self::assertGreaterThan(3000, filesize($tmp) ?: 0);
            } finally {
                @unlink($tmp);
            }
        } finally {
            @unlink($logo);
        }
    }

    #[RunInSeparateProcess]
    #[PreserveGlobalState(false)]
    public function testFooterWithoutPageNumber(): void
    {
        self::bootKernel();
        /** @var HtmlToWordConverter $converter */
        $converter = self::getContainer()->get(HtmlToWordConverter::class);

        $doc = $converter->convertWithOptions(
            '<p>Footer only text</p>',
            [
                'footer' => [
                    'enabled'          => true,
                    'text'             => 'Only footer',
                    'show_page_number' => false,
                ],
            ],
        );

        $tmp = sys_get_temp_dir() . '/htw_hf2_' . uniqid() . '.docx';
        try {
            $writer = \PhpOffice\PhpWord\IOFactory::createWriter($doc->phpWord(), 'Word2007');
            $writer->save($tmp);
            self::assertGreaterThan(2000, filesize($tmp) ?: 0);
        } finally {
            @unlink($tmp);
        }
    }
}
