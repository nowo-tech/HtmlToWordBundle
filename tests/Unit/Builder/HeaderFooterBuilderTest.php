<?php

declare(strict_types=1);

namespace Nowo\HtmlToWordBundle\Tests\Unit\Builder;

use Nowo\HtmlToWordBundle\Builder\HeaderFooterBuilder;
use Nowo\HtmlToWordBundle\Config\ResolvedConfig;
use PhpOffice\PhpWord\Element\Footer;
use PhpOffice\PhpWord\Element\Header;
use PhpOffice\PhpWord\Element\PreserveText;
use PhpOffice\PhpWord\Element\Section;
use PhpOffice\PhpWord\Element\Text;
use PhpOffice\PhpWord\PhpWord;
use PHPUnit\Framework\TestCase;

final class HeaderFooterBuilderTest extends TestCase
{
    public function testDisabledHeaderAndFooterAddNothing(): void
    {
        $section = $this->apply([]);

        self::assertSame([], $section->getHeaders());
        self::assertSame([], $section->getFooters());
        self::assertFalse($section->hasDifferentFirstPage());
    }

    public function testHeaderShowPageNumberAddsPageField(): void
    {
        $section = $this->apply(['header' => ['enabled' => true, 'text' => 'Hdr', 'show_page_number' => true]]);

        $headers = array_values($section->getHeaders());
        self::assertCount(1, $headers);
        self::assertSame(['Text', 'PreserveText'], $this->elementTypes($headers[0]));
        self::assertFalse($section->hasDifferentFirstPage());
    }

    public function testHeaderWithoutPageNumberByDefault(): void
    {
        $section = $this->apply(['header' => ['enabled' => true, 'text' => 'Hdr']]);

        self::assertSame(['Text'], $this->elementTypes(array_values($section->getHeaders())[0]));
    }

    public function testHeaderDifferentFirstPageBlanksFirstHeaderAndRepeatsFooter(): void
    {
        $section = $this->apply([
            'header' => ['enabled' => true, 'text' => 'Hdr', 'different_first_page' => true],
            'footer' => ['enabled' => true, 'text' => 'Ftr', 'show_page_number' => true],
        ]);

        self::assertTrue($section->hasDifferentFirstPage());
        $headers = $this->byType($section->getHeaders());
        self::assertSame(['Text'], $this->elementTypes($headers[Header::AUTO]));
        self::assertSame([], $this->elementTypes($headers[Header::FIRST]));

        $footers = $this->byType($section->getFooters());
        self::assertSame(['Text', 'PreserveText'], $this->elementTypes($footers[Header::AUTO]));
        self::assertSame(['Text', 'PreserveText'], $this->elementTypes($footers[Header::FIRST]));
    }

    public function testFooterDifferentFirstPageBlanksFirstFooterAndRepeatsHeader(): void
    {
        $section = $this->apply([
            'header' => ['enabled' => true, 'text' => 'Hdr'],
            'footer' => ['enabled' => true, 'show_page_number' => true, 'different_first_page' => true],
        ]);

        self::assertTrue($section->hasDifferentFirstPage());
        $headers = $this->byType($section->getHeaders());
        self::assertSame(['Text'], $this->elementTypes($headers[Header::FIRST]));
        $footers = $this->byType($section->getFooters());
        self::assertSame(['PreserveText'], $this->elementTypes($footers[Header::AUTO]));
        self::assertSame([], $this->elementTypes($footers[Header::FIRST]));
    }

    public function testDifferentFirstPageIgnoredWhenSideDisabled(): void
    {
        $section = $this->apply([
            'header' => ['enabled' => false, 'different_first_page' => true],
            'footer' => ['enabled' => true, 'show_page_number' => false, 'text' => 'Ftr'],
        ]);

        self::assertFalse($section->hasDifferentFirstPage());
        self::assertSame([], $section->getHeaders());
        self::assertCount(1, $section->getFooters());
    }

    /**
     * @param array<string, mixed> $config
     */
    private function apply(array $config): Section
    {
        $section = (new PhpWord())->addSection();
        (new HeaderFooterBuilder())->apply($section, ResolvedConfig::fromArray($config));

        return $section;
    }

    /**
     * @param array<int, Footer> $containers
     *
     * @return array<string, Footer>
     */
    private function byType(array $containers): array
    {
        $out = [];
        foreach ($containers as $c) {
            $out[$c->getType()] = $c;
        }
        self::assertCount(2, $out);

        return $out;
    }

    /**
     * @return list<string>
     */
    private function elementTypes(Footer $container): array
    {
        $types = [];
        foreach ($container->getElements() as $el) {
            $types[] = match (true) {
                $el instanceof PreserveText => 'PreserveText',
                $el instanceof Text         => 'Text',
                default                     => $el::class,
            };
        }

        return $types;
    }
}
