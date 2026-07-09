<?php

declare(strict_types=1);

namespace Nowo\HtmlToWordBundle\Builder;

use Nowo\HtmlToWordBundle\Config\ResolvedConfig;
use Nowo\HtmlToWordBundle\Exception\ImageResolveException;
use Nowo\HtmlToWordBundle\Security\RemoteImageHostPolicy;

use function sprintf;

/**
 * Resolves <img src> to a temporary local path for PHPWord.
 *
 * Supports data URIs, http(s) URLs, and readable local paths (Unix, Windows drive letters, project-relative when CWD allows).
 *
 * @author Héctor Franco Aceituno <hectorfranco@nowo.tech>
 */
final class ImageResolver implements ImageResolverInterface
{
    /**
     * @throws ImageResolveException
     */
    public function resolveToTempPath(string $src, ResolvedConfig $config): string
    {
        $src = trim($src);
        if ($src === '') {
            throw new ImageResolveException('Empty image src.');
        }

        if (str_starts_with($src, 'data:')) {
            $path = $this->fromDataUri($src);
            $this->assertRasterImage($path, true);

            return $path;
        }

        if ($this->isHttpUrl($src)) {
            return $this->fromHttpUrl($src, $config);
        }

        if (@is_readable($src)) {
            $this->assertRasterImage($src, false);

            return $src;
        }

        throw new ImageResolveException(sprintf('Unsupported or unreadable image src: %s', $src));
    }

    private function isHttpUrl(string $src): bool
    {
        $lower = strtolower($src);

        return str_starts_with($lower, 'http://') || str_starts_with($lower, 'https://');
    }

    /**
     * @throws ImageResolveException
     */
    private function fromHttpUrl(string $src, ResolvedConfig $config): string
    {
        if (!(bool) $config->get('images.resolve_remote', false)) {
            throw new ImageResolveException('Remote image resolution disabled by profile.');
        }

        /** @var list<string> $allowlist */
        $allowlist = $config->get('images.remote_host_allowlist', []);
        if (!RemoteImageHostPolicy::isAllowed($src, $allowlist)) {
            throw new ImageResolveException('Remote image host is not in the configured allowlist.');
        }

        $ctx = stream_context_create([
            'http'  => ['timeout' => 10],
            'https' => ['timeout' => 10],
        ]);
        $raw = @file_get_contents($src, false, $ctx);
        if ($raw === false) {
            throw new ImageResolveException(sprintf('Could not download image: %s', $src));
        }

        $tmp = tempnam(sys_get_temp_dir(), 'htw_img_');
        // @codeCoverageIgnoreStart
        if ($tmp === false) {
            throw new ImageResolveException('Could not create temp file.');
        }
        // @codeCoverageIgnoreEnd

        file_put_contents($tmp, $raw);
        $this->assertRasterImage($tmp, true);

        return $tmp;
    }

    /**
     * @throws ImageResolveException
     */
    private function fromDataUri(string $src): string
    {
        if (!preg_match('#^data:image/[^;]+;base64,(.+)$#', $src, $m)) {
            throw new ImageResolveException('Invalid data URI image.');
        }
        $bin = base64_decode($m[1], true);
        if ($bin === false) {
            throw new ImageResolveException('Invalid base64 image data.');
        }
        $tmp = tempnam(sys_get_temp_dir(), 'htw_b64_');
        // @codeCoverageIgnoreStart
        if ($tmp === false) {
            throw new ImageResolveException('Could not create temp file.');
        }
        // @codeCoverageIgnoreEnd
        file_put_contents($tmp, $bin);

        return $tmp;
    }

    /**
     * @throws ImageResolveException
     */
    private function assertRasterImage(string $path, bool $unlinkTempOnFailure): void
    {
        if (ImageSignatureValidator::isRasterImage($path)) {
            return;
        }

        if ($unlinkTempOnFailure && str_starts_with($path, sys_get_temp_dir())) {
            @unlink($path);
        }

        throw new ImageResolveException('Resolved file is not a supported raster image (PNG, JPEG, GIF, WebP). Remote URLs that return HTML or JSON are rejected.');
    }
}
