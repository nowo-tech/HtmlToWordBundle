<?php

declare(strict_types=1);

namespace Nowo\HtmlToWordBundle\Security;

use function is_array;
use function is_string;
use function ltrim;
use function parse_url;
use function preg_match;
use function rtrim;
use function str_contains;
use function str_ends_with;
use function str_starts_with;
use function strtolower;
use function trim;

use const PHP_URL_HOST;

/**
 * Validates remote image URLs against an optional host allowlist.
 *
 * Entries are matched against the parsed URL host only (never against the full URL):
 * - {@code example.com}: the host itself or any subdomain ({@code cdn.example.com}); {@code example.com.evil.net} does not match;
 * - {@code .example.com}: subdomains only;
 * - {@code https://cdn.example.com/...}: the host of that URL, exact match;
 * - {@code #regex#}: matched against the host (e.g. {@code #^cdn[0-9]+\.example\.com$#}).
 */
final class RemoteImageHostPolicy
{
    /**
     * @param list<string> $allowlist Hostnames (exact or parent domain) or regex patterns (prefix #) applied to the host
     */
    public static function isAllowed(string $url, array $allowlist): bool
    {
        if ($allowlist === []) {
            return false;
        }

        $host = parse_url($url, PHP_URL_HOST);
        if (!is_string($host) || $host === '') {
            return false;
        }

        $hostLower = self::normalizeHost($host);

        foreach ($allowlist as $pattern) {
            if ($pattern === '') {
                continue;
            }
            if (str_starts_with($pattern, '#')) {
                if (@preg_match($pattern, $hostLower) === 1) {
                    return true;
                }
                continue;
            }
            if (self::hostMatches($hostLower, $pattern)) {
                return true;
            }
        }

        return false;
    }

    private static function hostMatches(string $host, string $pattern): bool
    {
        if (str_contains($pattern, '://')) {
            $parts = parse_url($pattern);
            if (!is_array($parts) || !isset($parts['host']) || $parts['host'] === '') {
                return false;
            }

            return self::normalizeHost($parts['host']) === $host;
        }

        $patternLower = strtolower(rtrim(trim($pattern), '.'));
        if (str_starts_with($patternLower, '.')) {
            return $patternLower !== '.' && str_ends_with($host, $patternLower);
        }
        $patternLower = ltrim($patternLower, '.');

        return $patternLower !== '' && ($host === $patternLower || str_ends_with($host, '.' . $patternLower));
    }

    private static function normalizeHost(string $host): string
    {
        return strtolower(rtrim(trim($host, '[]'), '.'));
    }
}
