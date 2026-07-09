<?php

declare(strict_types=1);

namespace Nowo\HtmlToWordBundle\Security;

use function is_string;
use function preg_match;
use function str_contains;
use function str_starts_with;
use function strtolower;

use const PHP_URL_HOST;

/**
 * Validates remote image URLs against an optional host allowlist.
 */
final class RemoteImageHostPolicy
{
    /**
     * @param list<string> $allowlist Hostnames/substrings or regex patterns (prefix #)
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

        $hostLower = strtolower($host);

        foreach ($allowlist as $pattern) {
            if ($pattern === '') {
                continue;
            }
            if (str_starts_with($pattern, '#')) {
                if (preg_match($pattern, $url) === 1) {
                    return true;
                }
                continue;
            }
            $patternLower = strtolower($pattern);
            if ($hostLower === $patternLower || str_contains($hostLower, $patternLower) || str_contains($url, $pattern)) {
                return true;
            }
        }

        return false;
    }
}
