<?php

declare(strict_types=1);

namespace Nowo\HtmlToWordBundle\Tests\Unit\Builder;

use Nowo\HtmlToWordBundle\Builder\ImageResolver;
use Nowo\HtmlToWordBundle\Config\ResolvedConfig;
use Nowo\HtmlToWordBundle\Exception\ImageResolveException;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Process\Process;

use function is_string;

use const PHP_BINARY;

final class ImageResolverTest extends TestCase
{
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
            $path = (new ImageResolver())->resolveToTempPath($tmp, ResolvedConfig::fromArray([]));
            self::assertSame($tmp, $path);
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
            (new ImageResolver())->resolveToTempPath($tmp, ResolvedConfig::fromArray([]));
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
        (new ImageResolver())->resolveToTempPath(
            'http://127.0.0.1:9/no-image.png',
            ResolvedConfig::fromArray([
                'images' => [
                    'resolve_remote'        => true,
                    'remote_host_allowlist' => ['127.0.0.1'],
                ],
            ]),
        );
    }

    public function testRemoteDownloadSuccessWithClampedTimeout(): void
    {
        $png = base64_decode(self::PNG_1X1, true);
        self::assertNotFalse($png);

        $docRoot = sys_get_temp_dir() . '/htw_http_' . uniqid('', true);
        self::assertTrue(mkdir($docRoot));
        $imgPath = $docRoot . '/pixel.png';
        file_put_contents($imgPath, $png);

        $port = random_int(28000, 28999);
        $cmd  = [
            PHP_BINARY,
            '-S',
            '127.0.0.1:' . $port,
            '-t',
            $docRoot,
        ];
        $process = new Process($cmd);
        $process->setTimeout(30);
        $process->setIdleTimeout(30);
        $process->start();

        $url    = 'http://127.0.0.1:' . $port . '/pixel.png';
        $tmpOut = null;
        try {
            $ready = false;
            for ($i = 0; $i < 50; ++$i) {
                usleep(50_000);
                $probe = @file_get_contents($url);
                if ($probe !== false) {
                    $ready = true;
                    break;
                }
            }
            self::assertTrue($ready, 'Built-in PHP server did not become ready');

            $tmpOut = (new ImageResolver())->resolveToTempPath(
                $url,
                ResolvedConfig::fromArray([
                    'images' => [
                        'resolve_remote'        => true,
                        'remote_host_allowlist' => ['127.0.0.1'],
                        'remote_timeout'        => 0.01,
                    ],
                ]),
            );
            self::assertFileExists($tmpOut);
            self::assertGreaterThan(10, filesize($tmpOut) ?: 0);
        } finally {
            if (is_string($tmpOut)) {
                @unlink($tmpOut);
            }
            $process->stop(1);
            @unlink($imgPath);
            @rmdir($docRoot);
        }
    }
}
