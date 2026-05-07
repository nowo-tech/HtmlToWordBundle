<?php

declare(strict_types=1);

namespace Nowo\HtmlToWordBundle\Builder;

use Nowo\HtmlToWordBundle\Config\ResolvedConfig;
use Nowo\HtmlToWordBundle\Exception\ImageResolveException;

use function sprintf;

use const FILTER_VALIDATE_URL;

/**
 * Resolves <img src> to a temporary local path for PHPWord.
 *
 * @author Héctor Franco Aceituno <hectorfranco@nowo.tech>
 */
final class ImageResolver
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
            return $this->fromDataUri($src);
        }

        if (str_starts_with($src, '/') && is_readable($src)) {
            return $src;
        }

        if (!filter_var($src, FILTER_VALIDATE_URL)) {
            throw new ImageResolveException(sprintf('Unsupported image src: %s', $src));
        }

        if (!(bool) $config->get('images.resolve_remote', true)) {
            throw new ImageResolveException('Remote image resolution disabled by profile.');
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
}
