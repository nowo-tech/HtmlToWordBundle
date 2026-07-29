<?php

declare(strict_types=1);

namespace Nowo\HtmlToWordBundle\Tests\Unit\Security;

use Nowo\HtmlToWordBundle\Security\RemoteImageHostPolicy;
use PHPUnit\Framework\TestCase;

final class RemoteImageHostPolicyTest extends TestCase
{
    public function testEmptyAllowlistDenies(): void
    {
        self::assertFalse(RemoteImageHostPolicy::isAllowed('https://example.com/a.png', []));
    }

    public function testHostSubstringAllows(): void
    {
        self::assertTrue(RemoteImageHostPolicy::isAllowed('https://cdn.example.com/a.png', ['example.com']));
    }

    public function testUrlWithoutHostDenies(): void
    {
        self::assertFalse(RemoteImageHostPolicy::isAllowed('http:///no-host', ['example.com']));
        self::assertFalse(RemoteImageHostPolicy::isAllowed('not-a-url', ['example.com']));
    }

    public function testEmptyPatternSkippedAndNoMatchDenies(): void
    {
        self::assertFalse(RemoteImageHostPolicy::isAllowed('https://evil.test/x.png', ['', 'good.example']));
    }

    public function testRegexPatternAllows(): void
    {
        self::assertTrue(RemoteImageHostPolicy::isAllowed(
            'https://cdn.example.com/a.png',
            ['#^https://cdn\\.example\\.com/#'],
        ));
        self::assertFalse(RemoteImageHostPolicy::isAllowed(
            'https://other.example.com/a.png',
            ['#^https://cdn\\.example\\.com/#'],
        ));
    }

    public function testExactHostMatchAllows(): void
    {
        self::assertTrue(RemoteImageHostPolicy::isAllowed('https://Example.COM/a.png', ['example.com']));
    }
}
