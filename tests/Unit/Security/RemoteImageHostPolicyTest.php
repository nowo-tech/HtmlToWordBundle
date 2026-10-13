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

    public function testSubdomainOfAllowedHostAllows(): void
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

    public function testRegexPatternMatchesHostOnly(): void
    {
        self::assertTrue(RemoteImageHostPolicy::isAllowed('https://cdn.example.com/a.png', ['#^cdn\\.example\\.com$#']));
        self::assertFalse(RemoteImageHostPolicy::isAllowed('https://other.example.com/a.png', ['#^cdn\\.example\\.com$#']));
        // Regex is applied to the host, so a URL-shaped regex can no longer be satisfied via path/query.
        self::assertFalse(RemoteImageHostPolicy::isAllowed('http://169.254.169.254/?u=https://cdn.example.com/', ['#https://cdn\\.example\\.com/#']));
        self::assertFalse(RemoteImageHostPolicy::isAllowed('http://169.254.169.254/cdn.example.com', ['#cdn\\.example\\.com#']));
        // Invalid regex never matches.
        self::assertFalse(RemoteImageHostPolicy::isAllowed('https://cdn.example.com/a.png', ['#invalid(']));
    }

    /** Audit bypasses: suffix host and allowlist value in the query string. */
    public function testHostSuffixAndQueryStringBypassesDenied(): void
    {
        self::assertFalse(RemoteImageHostPolicy::isAllowed('https://example.com.evil.net/a.png', ['example.com']));
        self::assertFalse(RemoteImageHostPolicy::isAllowed('http://169.254.169.254/?x=example.com', ['example.com']));
        self::assertFalse(RemoteImageHostPolicy::isAllowed('https://evil.net/example.com/a.png', ['example.com']));
        self::assertFalse(RemoteImageHostPolicy::isAllowed('https://notexample.com/a.png', ['example.com']));
        self::assertFalse(RemoteImageHostPolicy::isAllowed('https://example.com@evil.net/a.png', ['example.com']));
        self::assertTrue(RemoteImageHostPolicy::isAllowed('https://example.com./a.png', ['example.com']));
    }

    public function testLeadingDotMatchesSubdomainsOnly(): void
    {
        self::assertTrue(RemoteImageHostPolicy::isAllowed('https://cdn.example.com/a.png', ['.example.com']));
        self::assertFalse(RemoteImageHostPolicy::isAllowed('https://example.com/a.png', ['.example.com']));
        self::assertFalse(RemoteImageHostPolicy::isAllowed('https://example.com/a.png', ['.']));
        self::assertFalse(RemoteImageHostPolicy::isAllowed('https://example.com/a.png', ['..']));
    }

    public function testUrlEntryMatchesItsHostExactly(): void
    {
        self::assertTrue(RemoteImageHostPolicy::isAllowed('https://cdn.example.com/x/a.png', ['https://cdn.example.com/']));
        self::assertFalse(RemoteImageHostPolicy::isAllowed('https://img.cdn.example.com/a.png', ['https://cdn.example.com/']));
        self::assertFalse(RemoteImageHostPolicy::isAllowed('https://cdn.example.com/a.png', ['https:///nohost']));
    }

    public function testExactHostMatchAllows(): void
    {
        self::assertTrue(RemoteImageHostPolicy::isAllowed('https://Example.COM/a.png', ['example.com']));
    }
}
