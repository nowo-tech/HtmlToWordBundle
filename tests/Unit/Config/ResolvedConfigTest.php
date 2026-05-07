<?php

declare(strict_types=1);

namespace Nowo\HtmlToWordBundle\Tests\Unit\Config;

use Nowo\HtmlToWordBundle\Config\ResolvedConfig;
use PHPUnit\Framework\TestCase;

final class ResolvedConfigTest extends TestCase
{
    public function testGetDotPath(): void
    {
        $c = ResolvedConfig::fromArray([
            'page'        => ['margins' => ['top' => 100]],
            'strict_mode' => true,
        ]);

        self::assertSame(100, $c->get('page.margins.top'));
        self::assertNull($c->get('missing.key'));
        self::assertSame('fallback', $c->get('missing.key', 'fallback'));
        self::assertTrue($c->strictMode());
    }

    public function testAllReturnsMergedArray(): void
    {
        $data = ['a' => 1];
        self::assertSame($data, ResolvedConfig::fromArray($data)->all());
    }
}
