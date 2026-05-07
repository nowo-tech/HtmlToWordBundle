<?php

declare(strict_types=1);

namespace Nowo\HtmlToWordBundle\Parser;

use DOMDocument;
use DOMElement;
use DOMXPath;
use Nowo\HtmlToWordBundle\Builder\ImageResolverInterface;
use Nowo\HtmlToWordBundle\Config\ResolvedConfig;
use Nowo\HtmlToWordBundle\Exception\ImageResolveException;

use function is_file;
use function is_readable;
use function preg_match;
use function realpath;
use function str_starts_with;
use function strtolower;
use function trim;

/**
 * Before PhpWord runs: replaces {@code <img src="http(s)://...">} with an absolute local filesystem path
 * (temp file from {@see ImageResolverInterface::resolveToTempPath}). PhpWord’s HTML reader is most reliable
 * with paths, not {@code data:} URIs. Stored HTML can keep URLs; only the in-memory HTML passed to the
 * builder uses temp paths. {@see cleanupInlineSession()} runs after {@code DocxExporter} finishes
 * {@code IOFactory::createWriter()->save(...)} so PhpWord can copy image bytes into the DOCX first.
 *
 * @author Héctor Franco Aceituno <hectorfranco@nowo.tech>
 */
final class RemoteHttpImageInliner
{
    /** @var array<string, true> path => true */
    private array $sessionTempFiles = [];

    public function __construct(
        private HtmlParser $htmlParser,
        private ImageResolverInterface $imageResolver,
    ) {
    }

    /**
     * Deletes temp files registered during the last {@see inlineRemoteImages} pass (idempotent).
     */
    public function cleanupInlineSession(): void
    {
        foreach (array_keys($this->sessionTempFiles) as $path) {
            if ($path !== '' && is_file($path)) {
                @unlink($path);
            }
        }

        $this->sessionTempFiles = [];
    }

    /**
     * When {@code images.resolve_remote} is false, returns HTML unchanged (remote URLs are left as-is).
     */
    public function inlineRemoteImages(string $html, ResolvedConfig $config): string
    {
        $this->cleanupInlineSession();

        if (!(bool) $config->get('images.resolve_remote', true)) {
            return $html;
        }

        if ($html === '' || !preg_match('#<img\b[^>]*\bsrc\s*=\s*["\']?https?://#i', $html)) {
            return $html;
        }

        $dom   = $this->htmlParser->parse($html);
        $xpath = new DOMXPath($dom);

        /** @var array<string, string> $cache */
        $cache = [];

        foreach ($xpath->query('//img') ?: [] as $node) {
            if (!$node instanceof DOMElement) {
                continue;
            }

            $src = trim($node->getAttribute('src'));
            if ($src === '') {
                continue;
            }

            if (str_starts_with($src, '//')) {
                $src = 'https:' . $src;
            }

            if (!$this->isHttpUrl($src)) {
                continue;
            }

            if (isset($cache[$src])) {
                $node->setAttribute('src', $cache[$src]);

                continue;
            }

            try {
                $path = $this->imageResolver->resolveToTempPath($src, $config);
                if (!is_readable($path)) {
                    continue;
                }

                $absolute                          = realpath($path) ?: $path;
                $cache[$src]                       = $absolute;
                $this->sessionTempFiles[$absolute] = true;
                $node->setAttribute('src', $absolute);
            } catch (ImageResolveException) {
                continue;
            }
        }

        return $this->serializeBodyInnerHtml($dom);
    }

    private function isHttpUrl(string $src): bool
    {
        $lower = strtolower($src);

        return str_starts_with($lower, 'http://') || str_starts_with($lower, 'https://');
    }

    private function serializeBodyInnerHtml(DOMDocument $dom): string
    {
        $body = $dom->getElementsByTagName('body')->item(0);
        if (!$body instanceof DOMElement) {
            return '';
        }

        $out = '';
        foreach ($body->childNodes as $child) {
            $out .= $dom->saveHTML($child);
        }

        return $out;
    }
}
