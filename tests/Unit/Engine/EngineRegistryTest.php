<?php

declare(strict_types=1);

namespace Nowo\HtmlToWordBundle\Tests\Unit\Engine;

use Generator;
use Nowo\HtmlToWordBundle\Config\ResolvedConfig;
use Nowo\HtmlToWordBundle\Engine\EngineRegistry;
use Nowo\HtmlToWordBundle\Engine\WordEngineInterface;
use Nowo\HtmlToWordBundle\Exception\UnknownEngineException;
use Nowo\HtmlToWordBundle\Model\WordDocument;
use PhpOffice\PhpWord\PhpWord;
use PHPUnit\Framework\TestCase;

final class EngineRegistryTest extends TestCase
{
    public function testGetThrowsWhenMissing(): void
    {
        $this->expectException(UnknownEngineException::class);

        (new EngineRegistry([]))->get('phpword');
    }

    public function testRegisteredNamesListsEngines(): void
    {
        $a = new class implements WordEngineInterface {
            public function getName(): string
            {
                return 'a';
            }

            public function requiredPackages(): array
            {
                return [];
            }

            public function isAvailable(): bool
            {
                return true;
            }

            public function build(string $html, ResolvedConfig $config): WordDocument
            {
                return new WordDocument(new PhpWord(), $config, 'a');
            }
        };

        $b = new class implements WordEngineInterface {
            public function getName(): string
            {
                return 'b';
            }

            public function requiredPackages(): array
            {
                return [];
            }

            public function isAvailable(): bool
            {
                return true;
            }

            public function build(string $html, ResolvedConfig $config): WordDocument
            {
                return new WordDocument(new PhpWord(), $config, 'b');
            }
        };

        $reg = new EngineRegistry([$a, $b]);
        self::assertEqualsCanonicalizing(['a', 'b'], $reg->registeredNames());
        self::assertSame($a, $reg->get('a'));
    }

    public function testAcceptsTraversableEngines(): void
    {
        $e = new class implements WordEngineInterface {
            public function getName(): string
            {
                return 'z';
            }

            public function requiredPackages(): array
            {
                return [];
            }

            public function isAvailable(): bool
            {
                return true;
            }

            public function build(string $html, ResolvedConfig $config): WordDocument
            {
                return new WordDocument(new PhpWord(), $config, 'z');
            }
        };

        $gen = static function () use ($e): Generator {
            yield $e;
        };

        $reg = new EngineRegistry($gen());
        self::assertSame($e, $reg->get('z'));
    }
}
