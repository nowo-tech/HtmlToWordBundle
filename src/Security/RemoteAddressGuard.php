<?php

declare(strict_types=1);

namespace Nowo\HtmlToWordBundle\Security;

use Closure;
use Symfony\Component\HttpFoundation\IpUtils;

use function array_filter;
use function array_merge;
use function array_values;
use function dns_get_record;
use function filter_var;
use function gethostbynamel;
use function in_array;
use function is_array;
use function is_string;
use function preg_match;
use function rtrim;
use function str_contains;
use function str_ends_with;
use function strtolower;
use function trim;

use const DNS_AAAA;
use const FILTER_FLAG_IPV4;
use const FILTER_FLAG_NO_PRIV_RANGE;
use const FILTER_FLAG_NO_RES_RANGE;
use const FILTER_VALIDATE_IP;

/**
 * SSRF guard for remote image downloads: resolves a host and only returns an IP when every A/AAAA record is public.
 *
 * Unresolvable hosts are rejected (fail closed). Callers connect to the returned IP (DNS pinning) so a second lookup
 * cannot be rebound to an internal address.
 */
class RemoteAddressGuard
{
    /**
     * Non-public ranges blocked in addition to {@see IpUtils::PRIVATE_SUBNETS} and PHP's private/reserved filters.
     */
    private const BLOCKED_SUBNETS = [
        '0.0.0.0/8',
        '10.0.0.0/8',
        '100.64.0.0/10',
        '127.0.0.0/8',
        '169.254.0.0/16',
        '172.16.0.0/12',
        '192.0.0.0/24',
        '192.0.2.0/24',
        '192.168.0.0/16',
        '198.18.0.0/15',
        '198.51.100.0/24',
        '203.0.113.0/24',
        '224.0.0.0/4',
        '240.0.0.0/4',
        '::/128',
        '::1/128',
        '::ffff:0:0/96',
        '64:ff9b::/96',
        '64:ff9b:1::/48',
        '100::/64',
        '2001:db8::/32',
        '2002::/16',
        'fc00::/7',
        'fe80::/10',
        'ff00::/8',
    ];

    /**
     * @param (Closure(string): array<mixed>)|null $dnsResolver Optional resolver returning all A/AAAA addresses of a host (tests); defaults to system DNS
     */
    public function __construct(
        private readonly ?Closure $dnsResolver = null,
    ) {
    }

    /**
     * Returns one public IP of the host (IPv4 preferred), or null when the host is local, private, reserved or unresolvable.
     */
    public function resolvePublicIp(string $host): ?string
    {
        $host = strtolower(rtrim(trim($host, '[]'), '.'));
        if ($host === '' || $host === 'localhost' || str_ends_with($host, '.localhost')) {
            return null;
        }
        $ips = filter_var($host, FILTER_VALIDATE_IP) !== false ? [$host] : $this->resolveHost($host);
        if ($ips === []) {
            return null;
        }
        $preferred = null;
        foreach ($ips as $ip) {
            if (!self::isPublicIp($ip)) {
                return null;
            }
            if ($preferred === null || (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4) !== false && str_contains($preferred, ':'))) {
                $preferred = $ip;
            }
        }

        return $preferred;
    }

    /**
     * Returns true when the IP address is publicly routable (not private, loopback, link-local, CGNAT, reserved…).
     */
    public static function isPublicIp(string $ip): bool
    {
        if (filter_var($ip, FILTER_VALIDATE_IP) === false) {
            return false;
        }
        // IPv4-mapped / compatible IPv6 in dotted form (e.g. ::ffff:127.0.0.1) is never treated as public.
        if (str_contains($ip, ':') && preg_match('/^::(?:ffff:)?\d{1,3}(?:\.\d{1,3}){3}$/i', $ip) === 1) {
            return false;
        }
        if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE) === false) {
            return false;
        }

        return !IpUtils::checkIp($ip, array_merge(IpUtils::PRIVATE_SUBNETS, self::BLOCKED_SUBNETS));
    }

    /**
     * @return list<string> All A and AAAA addresses of the host (empty when it cannot be resolved)
     */
    private function resolveHost(string $host): array
    {
        if ($this->dnsResolver instanceof Closure) {
            return array_values(array_filter(($this->dnsResolver)($host), is_string(...)));
        }

        // @codeCoverageIgnoreStart
        $ips  = gethostbynamel($host) ?: [];
        $aaaa = @dns_get_record($host, DNS_AAAA);
        if (is_array($aaaa)) {
            foreach ($aaaa as $record) {
                if (isset($record['ipv6']) && is_string($record['ipv6']) && !in_array($record['ipv6'], $ips, true)) {
                    $ips[] = $record['ipv6'];
                }
            }
        }

        return $ips;
        // @codeCoverageIgnoreEnd
    }
}
