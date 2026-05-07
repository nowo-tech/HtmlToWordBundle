<?php

declare(strict_types=1);

namespace Nowo\HtmlToWordBundle\Tests\Unit\Converter;

use Nowo\HtmlToWordBundle\Config\ProfileResolver;
use Nowo\HtmlToWordBundle\Config\ResolvedConfig;
use Nowo\HtmlToWordBundle\Converter\HtmlToWordConverter;
use Nowo\HtmlToWordBundle\Engine\EngineRegistry;
use Nowo\HtmlToWordBundle\Engine\WordEngineInterface;
use Nowo\HtmlToWordBundle\Exception\EngineNotAvailableException;
use Nowo\HtmlToWordBundle\Model\WordDocument;
use PhpOffice\PhpWord\PhpWord;
use PHPUnit\Framework\TestCase;

final class HtmlToWordConverterTest extends TestCase
{
    public function testUnavailableEngineThrows(): void
    {
        $profiles = new ProfileResolver([
            'default' => ['strict_mode' => false],
        ], 'default');

        $engine = new class implements WordEngineInterface {
            public function getName(): string
            {
                return 'phpword';
            }

            public function requiredPackages(): array
            {
                return ['phpoffice/phpword'];
            }

            public function isAvailable(): bool
            {
                return false;
            }

            public function build(string $html, ResolvedConfig $config): WordDocument
            {
                return new WordDocument(new PhpWord(), $config, 'phpword');
            }
        };

        $registry  = new EngineRegistry([$engine]);
        $converter = new HtmlToWordConverter($profiles, $registry, 'phpword');

        $this->expectException(EngineNotAvailableException::class);
        $converter->convert('<p>x</p>');
    }

    public function testConvertWithInlineProfileBuildsWithResolvedConfigFromArray(): void
    {
        $profiles = new ProfileResolver([
            'default' => ['strict_mode' => false, 'export' => ['filename' => 'ignored.yaml']],
        ], 'default');

        $engine = $this->createMock(WordEngineInterface::class);
        $engine->method('getName')->willReturn('phpword');
        $engine->method('isAvailable')->willReturn(true);
        $engine->expects(self::once())->method('build')
            ->with(
                '<p>x</p>',
                self::callback(static fn(ResolvedConfig $c): bool => $c->get('strict_mode') === true
                    && $c->get('export.filename') === 'from-inline.docx'),
            )
            ->willReturn(new WordDocument(new PhpWord(), ResolvedConfig::fromArray(['strict_mode' => true]), 'phpword'));

        $registry  = new EngineRegistry([$engine]);
        $converter = new HtmlToWordConverter($profiles, $registry, 'phpword');

        $converter->convertWithInlineProfile('<p>x</p>', [
            'strict_mode' => true,
            'export'      => ['filename' => 'from-inline.docx'],
        ]);
    }
}
