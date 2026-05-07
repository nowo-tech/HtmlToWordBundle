<?php

declare(strict_types=1);

namespace Nowo\HtmlToWordBundle\Builder;

use function strlen;

/**
 * Detects common raster image signatures so PHPWord never embeds HTML/error payloads as pictures.
 *
 * @author Héctor Franco Aceituno <hectorfranco@nowo.tech>
 */
final class ImageSignatureValidator
{
    /**
     * PNG, JPEG, GIF89a/87a, WebP (RIFF/WebP).
     */
    public static function isRasterImage(string $path): bool
    {
        if (!is_readable($path)) {
            return false;
        }

        $handle = fopen($path, 'r');
        if ($handle === false) {
            return false;
        }

        $head = fread($handle, 16);
        fclose($handle);

        if ($head === false || strlen($head) < 4) {
            return false;
        }

        if (str_starts_with($head, "\x89PNG\r\n\x1a\n")) {
            return true;
        }

        if (str_starts_with($head, "\xff\xd8\xff")) {
            return true;
        }

        if (str_starts_with($head, 'GIF87a') || str_starts_with($head, 'GIF89a')) {
            return true;
        }

        return strlen($head) >= 12
            && str_starts_with($head, 'RIFF')
            && substr($head, 8, 4) === 'WEBP';
    }
}
