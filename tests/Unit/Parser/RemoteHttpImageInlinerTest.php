<?php

declare(strict_types=1);

namespace Nowo\HtmlToWordBundle\Tests\Unit\Parser;

use DOMDocument;
use Nowo\HtmlToWordBundle\Builder\ImageResolverInterface;
use Nowo\HtmlToWordBundle\Config\ResolvedConfig;
use Nowo\HtmlToWordBundle\Exception\ImageResolveException;
use Nowo\HtmlToWordBundle\Parser\HtmlParser;
use Nowo\HtmlToWordBundle\Parser\RemoteHttpImageInliner;
use PHPUnit\Framework\TestCase;
use ReflectionMethod;

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

    public function testEmptyHtmlOrNoHttpImgReturnsUnchanged(): void
    {
        $resolver = $this->createMock(ImageResolverInterface::class);
        $resolver->expects(self::never())->method('resolveToTempPath');
        $inliner = new RemoteHttpImageInliner(new HtmlParser(), $resolver);
        $cfg     = ResolvedConfig::fromArray(['images' => ['resolve_remote' => true]]);

        self::assertSame('', $inliner->inlineRemoteImages('', $cfg));
        self::assertSame('<p>no images</p>', $inliner->inlineRemoteImages('<p>no images</p>', $cfg));
        self::assertSame(
            '<img src="data:image/png;base64,xx"/>',
            $inliner->inlineRemoteImages('<img src="data:image/png;base64,xx"/>', $cfg),
        );
    }

    public function testSkipsEmptySrcProtocolRelativeNonHttpAndUnreadable(): void
    {
        $tmp = $this->createTempPng();
        try {
            $resolver = $this->createMock(ImageResolverInterface::class);
            $resolver->method('resolveToTempPath')->willReturnCallback(
                static function (string $src) use ($tmp): string {
                    if (str_contains($src, 'cdn.example.com')) {
                        return $tmp;
                    }
                    if (str_contains($src, 'missing')) {
                        return sys_get_temp_dir() . '/htw-missing-' . uniqid('', true) . '.png';
                    }

                    return $tmp;
                },
            );

            $inliner = new RemoteHttpImageInliner(new HtmlParser(), $resolver);
            $html    = <<<'HTML'
<img src="https://example.com/a.png"/>
<img src=""/>
<img src="//cdn.example.com/b.png"/>
<img src="data:image/png;base64,abc"/>
<img src="https://example.com/missing.png"/>
HTML;
            $out = $inliner->inlineRemoteImages($html, ResolvedConfig::fromArray([
                'images' => ['resolve_remote' => true],
            ]));

            self::assertStringContainsString((string) (realpath($tmp) ?: $tmp), $out);
            self::assertStringContainsString('data:image/png;base64,abc', $out);
            self::assertStringNotContainsString('cdn.example.com', $out);
        } finally {
            @unlink($tmp);
        }
    }

    public function testResolveExceptionLeavesSrcUnchanged(): void
    {
        $resolver = $this->createMock(ImageResolverInterface::class);
        $resolver->method('resolveToTempPath')->willThrowException(new ImageResolveException('nope'));

        $inliner = new RemoteHttpImageInliner(new HtmlParser(), $resolver);
        $html    = '<img src="https://example.com/a.png"/>';
        $out     = $inliner->inlineRemoteImages($html, ResolvedConfig::fromArray([
            'images' => ['resolve_remote' => true],
        ]));

        self::assertStringContainsString('https://example.com/a.png', $out);
    }

    public function testSerializeBodyInnerHtmlWithoutBodyReturnsEmpty(): void
    {
        $inliner = new RemoteHttpImageInliner(
            new HtmlParser(),
            $this->createMock(ImageResolverInterface::class),
        );
        $dom = new DOMDocument();
        $dom->loadXML('<root/>');

        $method = new ReflectionMethod(RemoteHttpImageInliner::class, 'serializeBodyInnerHtml');
        self::assertSame('', $method->invoke($inliner, $dom));
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
