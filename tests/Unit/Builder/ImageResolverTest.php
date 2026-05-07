<?php

declare(strict_types=1);

namespace Nowo\HtmlToWordBundle\Tests\Unit\Builder;

use Nowo\HtmlToWordBundle\Builder\ImageResolver;
use Nowo\HtmlToWordBundle\Config\ResolvedConfig;
use Nowo\HtmlToWordBundle\Exception\ImageResolveException;
use PHPUnit\Framework\TestCase;

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

    public function testNonUrlNonAbsoluteThrows(): void
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
            file_put_contents($tmp, 'not-really-png');
            $path = (new ImageResolver())->resolveToTempPath($tmp, ResolvedConfig::fromArray([]));
            self::assertSame($tmp, $path);
        } finally {
            @unlink($tmp);
        }
    }

    public function testRemoteDownloadFailureThrows(): void
    {
        $this->expectException(ImageResolveException::class);
        $this->expectExceptionMessage('Could not download');
        (new ImageResolver())->resolveToTempPath(
            'http://127.0.0.1:9/no-image.png',
            ResolvedConfig::fromArray(['images' => ['resolve_remote' => true]]),
        );
    }
}
