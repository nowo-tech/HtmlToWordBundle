<?php

declare(strict_types=1);

namespace Nowo\HtmlToWordBundle\Tests\Unit\Parser;

use Nowo\HtmlToWordBundle\Builder\ImageResolverInterface;
use Nowo\HtmlToWordBundle\Config\ResolvedConfig;
use Nowo\HtmlToWordBundle\Parser\HtmlParser;
use Nowo\HtmlToWordBundle\Parser\RemoteHttpImageInliner;
use PHPUnit\Framework\TestCase;

final class RemoteHttpImageInlinerTest extends TestCase
{
    private const PNG_1X1 = 'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mP8z8BQDwAEhQGAhKmMIQAAAABJRU5ErkJggg==';

    public function testRemoteDisabledLeavesUrls(): void
    {
        $tmp = $this->createTempPng();
        try {
            $resolver = $this->createMock(ImageResolverInterface::class);
            $resolver->expects(self::never())->method('resolveToTempPath');

            $inliner = new RemoteHttpImageInliner(new HtmlParser(), $resolver);
            $html    = '<p><img src="https://example.com/x.png" /></p>';
            $out     = $inliner->inlineRemoteImages($html, ResolvedConfig::fromArray([
                'images' => ['resolve_remote' => false],
            ]));

            self::assertSame($html, $out);
        } finally {
            @unlink($tmp);
        }
    }

    public function testReplacesHttpSrcWithAbsoluteTempPath(): void
    {
        $tmp = $this->createTempPng();
        try {
            $resolver = $this->createMock(ImageResolverInterface::class);
            $resolver->expects(self::once())->method('resolveToTempPath')->willReturn($tmp);

            $inliner = new RemoteHttpImageInliner(new HtmlParser(), $resolver);
            $html    = '<p><img src="https://example.com/a.png" alt="x" /></p>';
            $out     = $inliner->inlineRemoteImages($html, ResolvedConfig::fromArray([
                'images' => ['resolve_remote' => true],
            ]));

            $expected = realpath($tmp) ?: $tmp;
            self::assertStringContainsString($expected, $out);
            self::assertStringNotContainsString('https://example.com', $out);
            self::assertStringNotContainsString('data:', $out);

            $inliner->cleanupInlineSession();
            self::assertFileDoesNotExist($expected);
        } finally {
            @unlink($tmp);
        }
    }

    public function testCachesSameUrl(): void
    {
        $tmp = $this->createTempPng();
        try {
            $resolver = $this->createMock(ImageResolverInterface::class);
            $resolver->expects(self::once())->method('resolveToTempPath')->willReturn($tmp);

            $inliner = new RemoteHttpImageInliner(new HtmlParser(), $resolver);
            $html    = '<div><img src="https://example.com/a.png"/><img src="https://example.com/a.png"/></div>';
            $out     = $inliner->inlineRemoteImages($html, ResolvedConfig::fromArray([
                'images' => ['resolve_remote' => true],
            ]));

            $expected = realpath($tmp) ?: $tmp;
            self::assertSame(2, substr_count($out, $expected));
            self::assertStringNotContainsString('https://example.com', $out);

            $inliner->cleanupInlineSession();
            self::assertFileDoesNotExist($expected);
        } finally {
            @unlink($tmp);
        }
    }

    private function createTempPng(): string
    {
        $png = base64_decode(self::PNG_1X1, true);
        self::assertNotFalse($png);
        $tmp = tempnam(sys_get_temp_dir(), 'htw_inline_') . '.png';
        file_put_contents($tmp, $png);

        return $tmp;
    }
}
