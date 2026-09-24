<?php

declare(strict_types=1);

namespace Nowo\HtmlToWordBundle\Parser;

use DOMDocument;
use DOMElement;
use DOMXPath;
use Nowo\HtmlToWordBundle\Builder\ImageResolverInterface;
use Nowo\HtmlToWordBundle\Builder\TemporaryImageFiles;
use Nowo\HtmlToWordBundle\Config\ResolvedConfig;
use Nowo\HtmlToWordBundle\Exception\ImageResolveException;
use Nowo\HtmlToWordBundle\Model\WordDocument;

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
 * builder uses temp paths.
 *
 * Temp files are tracked per document in {@see TemporaryImageFiles}: the builder wraps each conversion in
 * {@see beginDocument()} / {@see endDocument()} and {@code DocxExporter} calls {@see releaseTemporaryFiles()}
 * after {@code IOFactory::createWriter()->save(...)} so PhpWord can copy image bytes into the DOCX first.
 *
 * @author Héctor Franco Aceituno <hectorfranco@nowo.tech>
 */
final class RemoteHttpImageInliner
{
    private readonly TemporaryImageFiles $temporaryFiles;

    public function __construct(
        private readonly HtmlParser $htmlParser,
        private readonly ImageResolverInterface $imageResolver,
        ?TemporaryImageFiles $temporaryFiles = null,
    ) {
        $this->temporaryFiles = $temporaryFiles ?? new TemporaryImageFiles();
    }

    public function beginDocument(): void
    {
        $this->temporaryFiles->beginCollection();
    }

    /**
     * @return list<string> temp files created since {@see beginDocument()}
     */
    public function endDocument(): array
    {
        return $this->temporaryFiles->endCollection();
    }

    /**
     * Ends the current collection and deletes its files (conversion failed).
     */
    public function abortDocument(): void
    {
        $this->temporaryFiles->release($this->temporaryFiles->endCollection());
    }

    /**
     * Deletes the temp images of one document once it has been written.
     */
    public function releaseTemporaryFiles(WordDocument $document): void
    {
        $this->temporaryFiles->release($document->temporaryFiles());
    }

    /**
     * Deletes every tracked temp image that was not released yet (idempotent).
     */
    public function cleanupInlineSession(): void
    {
        $this->temporaryFiles->releaseAll();
    }

    /**
     * When {@code images.resolve_remote} is false, returns HTML unchanged (remote URLs are left as-is).
     */
    public function inlineRemoteImages(string $html, ResolvedConfig $config): string
    {
        if (!(bool) $config->get('images.resolve_remote', false)) {
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
            // //img always resolves to elements; instanceof guard is defensive.
            // @codeCoverageIgnoreStart
            if (!$node instanceof DOMElement) {
                continue;
            }
            // @codeCoverageIgnoreEnd

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

                $absolute    = realpath($path) ?: $path;
                $cache[$src] = $absolute;
                $this->temporaryFiles->register($absolute);
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
