<?php

declare(strict_types=1);

namespace Nowo\HtmlToWordBundle\Tests\Unit\Transformer;

use DOMNode;
use Generator;
use Nowo\HtmlToWordBundle\Config\ResolvedConfig;
use Nowo\HtmlToWordBundle\Transformer\DocumentWalkerInterface;
use Nowo\HtmlToWordBundle\Transformer\TransformerChain;
use Nowo\HtmlToWordBundle\Transformer\TransformerInterface;
use PhpOffice\PhpWord\Element\AbstractContainer;
use PHPUnit\Framework\TestCase;

final class TransformerChainTest extends TestCase
{
    public function testOrdersByPriorityDescending(): void
    {
        $low = new class implements TransformerInterface {
            public function supports(string $element): bool
            {
                return $element === 'p';
            }

            public function getPriority(): int
            {
                return 1;
            }

            public function transform(DOMNode $node, AbstractContainer $container, ResolvedConfig $config, DocumentWalkerInterface $walker): void
            {
            }
        };

        $high = new class implements TransformerInterface {
            public function supports(string $element): bool
            {
                return $element === 'p';
            }

            public function getPriority(): int
            {
                return 50;
            }

            public function transform(DOMNode $node, AbstractContainer $container, ResolvedConfig $config, DocumentWalkerInterface $walker): void
            {
            }
        };

        $chain = new TransformerChain([$low, $high]);
        self::assertSame($high, $chain->firstSupporting('p'));
    }

    public function testFirstSupportingReturnsNull(): void
    {
        $chain = new TransformerChain([]);
        self::assertNull($chain->firstSupporting('p'));
    }

    public function testAcceptsGeneratorIterable(): void
    {
        $t = new class implements TransformerInterface {
            public function supports(string $element): bool
            {
                return $element === 'div';
            }

            public function getPriority(): int
            {
                return 10;
            }

            public function transform(DOMNode $node, AbstractContainer $container, ResolvedConfig $config, DocumentWalkerInterface $walker): void
            {
            }
        };

        $gen = static function () use ($t): Generator {
            yield $t;
        };

        $chain = new TransformerChain($gen());
        self::assertSame($t, $chain->firstSupporting('div'));
        self::assertCount(1, $chain->all());
    }
}
