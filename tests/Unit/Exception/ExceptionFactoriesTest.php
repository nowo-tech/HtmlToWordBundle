<?php

declare(strict_types=1);

namespace Nowo\HtmlToWordBundle\Tests\Unit\Exception;

use Nowo\HtmlToWordBundle\Engine\WordEngineInterface;
use Nowo\HtmlToWordBundle\Exception\EngineNotAvailableException;
use Nowo\HtmlToWordBundle\Exception\UnknownEngineException;
use PHPUnit\Framework\TestCase;
use RuntimeException;

final class ExceptionFactoriesTest extends TestCase
{
    public function testUnknownEngineMessageListsRegistered(): void
    {
        $e = UnknownEngineException::create('foo', ['a', 'b']);
        self::assertStringContainsString('foo', $e->getMessage());
        self::assertStringContainsString('a, b', $e->getMessage());
    }

    public function testUnknownEngineNoneRegistered(): void
    {
        $e = UnknownEngineException::create('foo', []);
        self::assertStringContainsString('(none)', $e->getMessage());
    }

    public function testEngineNotAvailableListsPackages(): void
    {
        $engine = new class implements WordEngineInterface {
            public function getName(): string
            {
                return 'x';
            }

            public function requiredPackages(): array
            {
                return ['pkg/a'];
            }

            public function isAvailable(): bool
            {
                return false;
            }

            public function build(string $html, \Nowo\HtmlToWordBundle\Config\ResolvedConfig $config): \Nowo\HtmlToWordBundle\Model\WordDocument
            {
                throw new RuntimeException('unused');
            }
        };

        $e = EngineNotAvailableException::forEngine($engine);
        self::assertStringContainsString('x', $e->getMessage());
        self::assertStringContainsString('pkg/a', $e->getMessage());
    }

    public function testEngineNotAvailableEmptyPackages(): void
    {
        $engine = new class implements WordEngineInterface {
            public function getName(): string
            {
                return 'y';
            }

            public function requiredPackages(): array
            {
                return [];
            }

            public function isAvailable(): bool
            {
                return false;
            }

            public function build(string $html, \Nowo\HtmlToWordBundle\Config\ResolvedConfig $config): \Nowo\HtmlToWordBundle\Model\WordDocument
            {
                throw new RuntimeException('unused');
            }
        };

        $e = EngineNotAvailableException::forEngine($engine);
        self::assertStringContainsString('documentation', $e->getMessage());
    }
}
