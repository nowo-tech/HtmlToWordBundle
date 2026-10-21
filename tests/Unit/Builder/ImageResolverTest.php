<?php

declare(strict_types=1);

namespace Nowo\HtmlToWordBundle\Tests\Unit\Builder;

use Nowo\HtmlToWordBundle\Builder\ImageResolver;
use Nowo\HtmlToWordBundle\Builder\TemporaryImageFiles;
use Nowo\HtmlToWordBundle\Config\ResolvedConfig;
use Nowo\HtmlToWordBundle\Exception\ImageResolveException;
use Nowo\HtmlToWordBundle\Security\RemoteAddressGuard;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Psr\Log\AbstractLogger;
use Stringable;
use Symfony\Component\Process\Process;

use const PHP_BINARY;
use const PHP_URL_HOST;
use const PHP_URL_PORT;

final class ImageResolverTest extends TestCase
{
    private ?Process $server = null;

    private string $docRoot = '';

    private string $serverUrl = '';

    private const PNG_1X1 = 'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mP8z8BQDwAEhQGAhKmMIQAAAABJRU5ErkJggg==';

    public function testDataUriWritesTempFile(): void
    {
        $r    = new ImageResolver();
        $path = $r->resolveToTempPath(
            'data:image/png;base64,' . self::PNG_1X1,
            ResolvedConfig::fromArray(['images' => ['resolve_remote' => true]]),
        );
        self::assertFileExists($path);
        self::assertGreaterThan(10, filesize($path) ?: 0);
        @unlink($path);
    }

    public function testEmptySrcThrows(): void
    {
        $this->expectException(ImageResolveException::class);
        (new ImageResolver())->resolveToTempPath('', ResolvedConfig::fromArray([]));
    }

    public function testInvalidDataUriThrows(): void
    {
        $this->expectException(ImageResolveException::class);
        (new ImageResolver())->resolveToTempPath('data:text/plain;base64,xx', ResolvedConfig::fromArray([]));
    }

    public function testInvalidBase64PayloadThrows(): void
    {
        $this->expectException(ImageResolveException::class);
        $this->expectExceptionMessage('Invalid base64');
        (new ImageResolver())->resolveToTempPath(
            'data:image/png;base64,!!!!',
            ResolvedConfig::fromArray([]),
        );
    }

    public function testNonExistentPathThrows(): void
    {
        $this->expectException(ImageResolveException::class);
        (new ImageResolver())->resolveToTempPath('not-a-url', ResolvedConfig::fromArray([]));
    }

    public function testRemoteDisabledThrows(): void
    {
        $this->expectException(ImageResolveException::class);
        (new ImageResolver())->resolveToTempPath(
            'https://example.com/image.png',
            ResolvedConfig::fromArray(['images' => ['resolve_remote' => false]]),
        );
    }

    public function testReadableAbsolutePathReturnsAsIs(): void
    {
        $tmp = tempnam(sys_get_temp_dir(), 'htw_img_read_');
        self::assertNotFalse($tmp);
        try {
            $png = base64_decode(self::PNG_1X1, true);
            self::assertNotFalse($png);
            file_put_contents($tmp, $png);
            $path = (new ImageResolver())->resolveToTempPath($tmp, ResolvedConfig::fromArray(['images' => ['local_base_dir' => sys_get_temp_dir()]]));
            self::assertSame(realpath($tmp), $path);
        } finally {
            @unlink($tmp);
        }
    }

    public function testAbsolutePathNonRasterThrows(): void
    {
        $tmp = tempnam(sys_get_temp_dir(), 'htw_img_bad_');
        self::assertNotFalse($tmp);
        try {
            file_put_contents($tmp, '<html>not an image</html>');
            $this->expectException(ImageResolveException::class);
            $this->expectExceptionMessage('not a supported raster image');
            (new ImageResolver())->resolveToTempPath($tmp, ResolvedConfig::fromArray(['images' => ['local_base_dir' => sys_get_temp_dir()]]));
        } finally {
            @unlink($tmp);
        }
    }

    public function testDataUriNonRasterPayloadThrows(): void
    {
        $text = base64_encode('<html>error</html>');
        $this->expectException(ImageResolveException::class);
        $this->expectExceptionMessage('not a supported raster image');
        (new ImageResolver())->resolveToTempPath(
            'data:image/png;base64,' . $text,
            ResolvedConfig::fromArray([]),
        );
    }

    public function testRemoteBlockedWhenAllowlistEmpty(): void
    {
        $this->expectException(ImageResolveException::class);
        $this->expectExceptionMessage('allowlist');
        (new ImageResolver())->resolveToTempPath(
            'https://example.com/image.png',
            ResolvedConfig::fromArray(['images' => ['resolve_remote' => true, 'remote_host_allowlist' => []]]),
        );
    }

    public function testRemoteDownloadFailureThrows(): void
    {
        $this->expectException(ImageResolveException::class);
        $this->expectExceptionMessage('Could not download image');
        (new ImageResolver())->resolveToTempPath(
            'http://127.0.0.1:9/no-image.png',
            ResolvedConfig::fromArray([
                'images' => [
                    'resolve_remote'               => true,
                    'remote_host_allowlist'        => ['127.0.0.1'],
                    'remote_allow_private_network' => true,
                ],
            ]),
        );
    }

    public function testRemoteDownloadsAreLoggedWithoutTheUrl(): void
    {
        $logger = new class extends AbstractLogger {
            /** @var list<array{0: mixed, 1: string, 2: array<mixed>}> */
            public array $records = [];

            /** @param array<mixed> $context */
            public function log(mixed $level, string|Stringable $message, array $context = []): void
            {
                $this->records[] = [$level, (string) $message, $context];
            }
        };
        $config = ResolvedConfig::fromArray([
            'images' => [
                'resolve_remote'               => true,
                'remote_host_allowlist'        => ['127.0.0.1'],
                'remote_allow_private_network' => true,
            ],
        ]);
        $resolver = new ImageResolver(null, null, $logger);

        $tmpOut = $resolver->resolveToTempPath($this->baseUrl() . '/pixel.png?token=s3cr3t', $config);
        @unlink($tmpOut);

        try {
            $resolver->resolveToTempPath('http://127.0.0.1:9/no-image.png?token=s3cr3t', $config);
            self::fail('Download must fail.');
        } catch (ImageResolveException) {
        }

        self::assertSame(['info', 'warning'], array_column($logger->records, 0));
        self::assertSame('127.0.0.1', $logger->records[0][2]['host']);
        self::assertGreaterThan(10, $logger->records[0][2]['bytes']);
        self::assertSame('Could not download image', $logger->records[1][2]['reason']);
        self::assertStringNotContainsString('s3cr3t', (string) json_encode($logger->records));
    }

    public function testRemoteDownloadSuccessWithClampedTimeout(): void
    {
        $tmpOut = (new ImageResolver())->resolveToTempPath(
            $this->baseUrl() . '/pixel.png',
            ResolvedConfig::fromArray([
                'images' => [
                    'resolve_remote'               => true,
                    'remote_host_allowlist'        => ['127.0.0.1'],
                    'remote_timeout'               => 0.01,
                    'remote_allow_private_network' => true,
                    'remote_max_bytes'             => 0,
                ],
            ]),
        );
        try {
            self::assertFileExists($tmpOut);
            self::assertGreaterThan(10, filesize($tmpOut) ?: 0);
        } finally {
            @unlink($tmpOut);
        }
    }

    /** Pinned connection: host resolved by the guard, request sent to the validated IP with the original Host header. */
    public function testRemoteDownloadPinnedToValidatedIp(): void
    {
        $guard = new class extends RemoteAddressGuard {
            public function resolvePublicIp(string $host): ?string
            {
                return $host === 'img.example.com' ? '127.0.0.1' : null;
            }
        };
        $port   = (string) parse_url($this->baseUrl(), PHP_URL_PORT);
        $tmpOut = (new ImageResolver(null, $guard))->resolveToTempPath(
            'http://img.example.com:' . $port . '/host-check.png?x=1',
            $this->remoteConfig(['img.example.com']),
        );
        try {
            self::assertFileExists($tmpOut);
        } finally {
            @unlink($tmpOut);
        }
    }

    /** Pinned IPv6 address is bracketed in the connect URL (connection itself fails: nothing listens there). */
    public function testRemoteDownloadPinnedIpv6Url(): void
    {
        $guard = new class extends RemoteAddressGuard {
            public function resolvePublicIp(string $host): string
            {
                return '::1';
            }
        };
        $this->expectException(ImageResolveException::class);
        $this->expectExceptionMessage('Could not download image');
        (new ImageResolver(null, $guard))->resolveToTempPath('http://v6.example.com:9/a.png', $this->remoteConfig(['v6.example.com']));
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function privateUrlProvider(): iterable
    {
        yield 'loopback' => ['http://127.0.0.1/a.png'];
        yield 'metadata' => ['http://169.254.169.254/latest/meta-data/'];
        yield '172.16/12' => ['http://172.16.5.4/a.png'];
        yield 'CGNAT' => ['http://100.64.0.1/a.png'];
        yield '0.0.0.0' => ['http://0.0.0.0/a.png'];
        yield 'ULA' => ['http://[fd00::1]/a.png'];
        yield 'IPv4-mapped' => ['http://[::ffff:127.0.0.1]/a.png'];
        yield 'localhost' => ['http://localhost/a.png'];
        yield 'sub.localhost' => ['http://a.localhost/a.png'];
    }

    #[DataProvider('privateUrlProvider')]
    public function testRemoteNonPublicAddressRejected(string $url): void
    {
        $host = (string) parse_url($url, PHP_URL_HOST);
        $this->expectException(ImageResolveException::class);
        $this->expectExceptionMessage('non-public');
        (new ImageResolver())->resolveToTempPath($url, $this->remoteConfig([trim($host, '[]')]));
    }

    public function testRemoteHostResolvingToPrivateIpRejected(): void
    {
        $guard = new RemoteAddressGuard(static fn (string $host): array => ['93.184.215.14', '10.1.2.3']);
        $this->expectException(ImageResolveException::class);
        $this->expectExceptionMessage('non-public');
        (new ImageResolver(null, $guard))->resolveToTempPath('https://img.example.com/a.png', $this->remoteConfig(['example.com']));
    }

    public function testRemoteUnresolvableHostRejected(): void
    {
        $guard = new RemoteAddressGuard(static fn (string $host): array => []);
        $this->expectException(ImageResolveException::class);
        $this->expectExceptionMessage('non-public or unknown');
        (new ImageResolver(null, $guard))->resolveToTempPath('https://aaaa-only.example.com/a.png', $this->remoteConfig(['example.com']));
    }

    public function testRemoteRedirectIsNotFollowed(): void
    {
        $this->expectException(ImageResolveException::class);
        $this->expectExceptionMessage('HTTP 302');
        (new ImageResolver())->resolveToTempPath($this->baseUrl() . '/redirect', $this->remoteConfig(['127.0.0.1'], true));
    }

    public function testRemoteNon2xxRejected(): void
    {
        $this->expectException(ImageResolveException::class);
        $this->expectExceptionMessage('HTTP 404');
        (new ImageResolver())->resolveToTempPath($this->baseUrl() . '/missing.png', $this->remoteConfig(['127.0.0.1'], true));
    }

    public function testRemoteDownloadAbortedAboveMaxBytes(): void
    {
        $this->expectException(ImageResolveException::class);
        $this->expectExceptionMessage('remote_max_bytes');
        (new ImageResolver())->resolveToTempPath(
            $this->baseUrl() . '/big.png',
            ResolvedConfig::fromArray(['images' => [
                'resolve_remote'               => true,
                'remote_host_allowlist'        => ['127.0.0.1'],
                'remote_allow_private_network' => true,
                'remote_max_bytes'             => 1000,
            ]]),
        );
    }

    public function testRemoteInvalidUrlRejected(): void
    {
        $this->expectException(ImageResolveException::class);
        (new ImageResolver())->resolveToTempPath('http://:80/a.png', $this->remoteConfig(['example.com']));
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function streamWrapperProvider(): iterable
    {
        yield 'file' => ['file:///etc/passwd'];
        yield 'ftp' => ['ftp://example.com/a.png'];
        yield 'php' => ['php://filter/resource=/etc/passwd'];
        yield 'phar' => ['phar:///tmp/x.phar/a.png'];
        yield 'glob' => ['glob:///etc/*'];
        yield 'expect' => ['expect://id'];
    }

    #[DataProvider('streamWrapperProvider')]
    public function testStreamWrappersRejected(string $src): void
    {
        $this->expectException(ImageResolveException::class);
        $this->expectExceptionMessage('Unsupported image src scheme');
        (new ImageResolver())->resolveToTempPath($src, ResolvedConfig::fromArray(['images' => ['local_base_dir' => '/']]));
    }

    public function testLocalPathRejectedWithoutBaseDir(): void
    {
        $tmp = $this->writePng(sys_get_temp_dir() . '/htw_nobase_' . uniqid('', true) . '.png');
        try {
            foreach (['/etc/passwd', '/var/www/secret.png', $tmp, 'C:\\Windows\\a.png'] as $src) {
                try {
                    (new ImageResolver())->resolveToTempPath($src, ResolvedConfig::fromArray([]));
                    self::fail('Expected rejection for ' . $src);
                } catch (ImageResolveException $e) {
                    self::assertStringContainsString('Unsupported or unreadable image src', $e->getMessage());
                }
            }
        } finally {
            @unlink($tmp);
        }
    }

    public function testLocalPathInsideBaseDirAcceptedAndTraversalRejected(): void
    {
        $base = sys_get_temp_dir() . '/htw_base_' . uniqid('', true);
        self::assertTrue(mkdir($base . '/img', 0o777, true));
        $inside  = $this->writePng($base . '/img/a.png');
        $outside = $this->writePng(sys_get_temp_dir() . '/htw_outside_' . uniqid('', true) . '.png');
        $config  = ResolvedConfig::fromArray(['images' => ['local_base_dir' => $base . '/img/']]);
        try {
            self::assertSame(realpath($inside), (new ImageResolver())->resolveToTempPath('a.png', $config));
            self::assertSame(realpath($inside), (new ImageResolver())->resolveToTempPath($inside, $config));
            foreach (['../../' . basename($outside), $outside, $base . '/img/../../' . basename($outside), $base . '/img/missing.png'] as $src) {
                try {
                    (new ImageResolver())->resolveToTempPath($src, $config);
                    self::fail('Expected rejection for ' . $src);
                } catch (ImageResolveException $e) {
                    self::assertStringContainsString('Unsupported or unreadable image src', $e->getMessage());
                }
            }
            $this->expectException(ImageResolveException::class);
            (new ImageResolver())->resolveToTempPath('a.png', ResolvedConfig::fromArray(['images' => ['local_base_dir' => $base . '/nope']]));
        } finally {
            @unlink($inside);
            @unlink($outside);
            @rmdir($base . '/img');
            @rmdir($base);
        }
    }

    /** Temp files tracked for the current conversion (remote images inlined before PhpWord) stay accepted. */
    public function testTrackedTemporaryFileAccepted(): void
    {
        $tmp     = $this->writePng(sys_get_temp_dir() . '/htw_tracked_' . uniqid('', true) . '.png');
        $tracker = new TemporaryImageFiles();
        try {
            $resolver = new ImageResolver($tracker);
            try {
                $resolver->resolveToTempPath($tmp, ResolvedConfig::fromArray([]));
                self::fail('Untracked temp file must be rejected');
            } catch (ImageResolveException) {
            }
            $tracker->register($tmp);
            self::assertSame($tmp, $resolver->resolveToTempPath($tmp, ResolvedConfig::fromArray([])));
        } finally {
            @unlink($tmp);
        }
    }

    /**
     * @param list<string> $allowlist
     */
    private function remoteConfig(array $allowlist, bool $allowPrivate = false): ResolvedConfig
    {
        return ResolvedConfig::fromArray(['images' => [
            'resolve_remote'               => true,
            'remote_host_allowlist'        => $allowlist,
            'remote_timeout'               => 2.0,
            'remote_allow_private_network' => $allowPrivate,
        ]]);
    }

    private function writePng(string $path): string
    {
        file_put_contents($path, (string) base64_decode(self::PNG_1X1, true));

        return $path;
    }

    /** Starts (once per test) a PHP built-in server with a small router and returns its base URL. */
    private function baseUrl(): string
    {
        if ($this->server instanceof Process) {
            return $this->serverUrl;
        }
        $this->docRoot = sys_get_temp_dir() . '/htw_http_' . uniqid('', true);
        mkdir($this->docRoot);
        file_put_contents($this->docRoot . '/router.php', '<?php
$png = base64_decode(' . var_export(self::PNG_1X1, true) . ');
$path = parse_url($_SERVER["REQUEST_URI"], PHP_URL_PATH);
if ($path === "/pixel.png") { header("Content-Type: image/png"); echo $png; return true; }
if ($path === "/host-check.png") {
    if (!str_starts_with($_SERVER["HTTP_HOST"] ?? "", "img.example.com:")) { http_response_code(400); return true; }
    header("Content-Type: image/png"); echo $png; return true;
}
if ($path === "/redirect") { header("Location: http://169.254.169.254/latest/meta-data/", true, 302); return true; }
if ($path === "/big.png") { header("Content-Type: image/png"); echo $png . str_repeat("A", 200000); return true; }
http_response_code(404);
return true;
');

        $port            = random_int(28000, 28999);
        $this->serverUrl = 'http://127.0.0.1:' . $port;
        $this->server    = new Process([PHP_BINARY, '-S', '127.0.0.1:' . $port, $this->docRoot . '/router.php']);
        $this->server->setTimeout(120);
        $this->server->start();
        for ($i = 0; $i < 50; ++$i) {
            usleep(50_000);
            if (@file_get_contents($this->serverUrl . '/pixel.png') !== false) {
                return $this->serverUrl;
            }
        }
        self::fail('Built-in PHP server did not become ready');
    }

    protected function tearDown(): void
    {
        if ($this->server instanceof Process) {
            $this->server->stop(1);
            $this->server = null;
            @unlink($this->docRoot . '/router.php');
            @rmdir($this->docRoot);
        }
        parent::tearDown();
    }
}
