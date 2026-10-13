<?php

declare(strict_types=1);

namespace Nowo\HtmlToWordBundle\Tests\Unit\Security;

use Nowo\HtmlToWordBundle\Security\RemoteAddressGuard;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class RemoteAddressGuardTest extends TestCase
{
    /**
     * @return iterable<string, array{string}>
     */
    public static function nonPublicProvider(): iterable
    {
        foreach ([
            '127.0.0.1', '10.0.0.1', '172.16.0.1', '172.31.255.254', '192.168.1.1', '169.254.169.254',
            '0.0.0.0', '0.1.2.3', '100.64.0.1', '100.127.255.254', '198.18.0.1', '224.0.0.1', '255.255.255.255',
            '::', '::1', 'fc00::1', 'fd12:3456::1', 'fe80::1', '::ffff:127.0.0.1', '::ffff:8.8.8.8', '::ffff:7f00:1',
            '64:ff9b::a00:1', 'not-an-ip',
        ] as $ip) {
            yield $ip => [$ip];
        }
    }

    #[DataProvider('nonPublicProvider')]
    public function testNonPublicIps(string $ip): void
    {
        self::assertFalse(RemoteAddressGuard::isPublicIp($ip));
        self::assertNull((new RemoteAddressGuard(static fn (): array => []))->resolvePublicIp($ip));
    }

    public function testPublicIps(): void
    {
        self::assertTrue(RemoteAddressGuard::isPublicIp('8.8.8.8'));
        self::assertTrue(RemoteAddressGuard::isPublicIp('2606:4700:4700::1111'));
        $guard = new RemoteAddressGuard(static fn (): array => []);
        self::assertSame('8.8.8.8', $guard->resolvePublicIp('8.8.8.8'));
        self::assertSame('2606:4700:4700::1111', $guard->resolvePublicIp('[2606:4700:4700::1111]'));
    }

    public function testLocalhostNamesBlocked(): void
    {
        $guard = new RemoteAddressGuard(static fn (): array => ['8.8.8.8']);
        self::assertNull($guard->resolvePublicIp('localhost'));
        self::assertNull($guard->resolvePublicIp('LOCALHOST.'));
        self::assertNull($guard->resolvePublicIp('app.localhost'));
        self::assertNull($guard->resolvePublicIp(''));
    }

    public function testAllRecordsMustBePublicAndIpv4Preferred(): void
    {
        self::assertNull((new RemoteAddressGuard(static fn (): array => ['8.8.8.8', 'fd00::1']))->resolvePublicIp('mixed.example'));
        self::assertNull((new RemoteAddressGuard(static fn (): array => []))->resolvePublicIp('aaaa-only.example'));
        self::assertSame('8.8.4.4', (new RemoteAddressGuard(static fn (): array => ['2606:4700::1', 42, '8.8.4.4']))->resolvePublicIp('dual.example'));
        self::assertSame('2606:4700::1', (new RemoteAddressGuard(static fn (): array => ['2606:4700::1']))->resolvePublicIp('v6.example'));
    }
}
