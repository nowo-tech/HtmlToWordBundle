<?php

declare(strict_types=1);

namespace Nowo\HtmlToWordBundle\Tests\Unit\Parser;

use DOMDocument;
use Nowo\HtmlToWordBundle\Parser\HtmlSanitizer;
use PHPUnit\Framework\TestCase;

final class HtmlSanitizerTest extends TestCase
{
    public function testRemovesScriptTags(): void
    {
        $s   = new HtmlSanitizer();
        $out = $s->sanitize('<p>a</p><script>alert(1)</script>');
        self::assertStringNotContainsString('<script', strtolower($out));
        self::assertStringContainsString('<p>a</p>', $out);
    }

    public function testSanitizeDomRemovesOnClick(): void
    {
        $s   = new HtmlSanitizer();
        $dom = new DOMDocument();
        $dom->loadHTML('<div onclick="bad()">x</div>');
        $s->sanitizeDom($dom);
        $html = $dom->saveHTML();
        self::assertStringNotContainsString('onclick', strtolower((string) $html));
    }

    public function testSanitizeRemovesIframe(): void
    {
        $s   = new HtmlSanitizer();
        $out = $s->sanitize('<p>a</p><iframe src="https://x"></iframe>');
        self::assertStringNotContainsString('iframe', strtolower($out));
    }

    public function testSanitizeRemovesStyleBlock(): void
    {
        $s   = new HtmlSanitizer();
        $out = $s->sanitize('<style>body{}</style><p>x</p>');
        self::assertStringNotContainsString('<style', strtolower($out));
    }
}
