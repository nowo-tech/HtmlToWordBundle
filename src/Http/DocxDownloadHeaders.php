<?php

declare(strict_types=1);

namespace Nowo\HtmlToWordBundle\Http;

use Nowo\HtmlToWordBundle\Export\DocxExporter;
use Symfony\Component\HttpFoundation\HeaderUtils;
use Symfony\Component\HttpFoundation\ResponseHeaderBag;

use function preg_replace;
use function trim;

/**
 * Download headers shared by {@see DocxResponseFactory} and the deprecated response methods of
 * {@see DocxExporter}, so both paths send the same headers.
 *
 * @internal
 *
 * @author Héctor Franco Aceituno <hectorfranco@nowo.tech>
 */
final class DocxDownloadHeaders
{
    public const CONTENT_TYPE = 'application/vnd.openxmlformats-officedocument.wordprocessingml.document';

    /**
     * @return array{Content-Type: string, Content-Disposition: string}
     */
    public static function for(string $filename): array
    {
        return [
            'Content-Type'        => self::CONTENT_TYPE,
            'Content-Disposition' => self::contentDisposition($filename),
        ];
    }

    /**
     * Builds an RFC 6266 attachment header (quoted ASCII fallback + filename* UTF-8) via {@see HeaderUtils::makeDisposition()},
     * so quotes, CR/LF, path separators or non-ASCII characters in export.filename cannot break or inject headers.
     */
    public static function contentDisposition(string $filename): string
    {
        // Control characters (incl. CR/LF) and path separators are never part of a download name.
        $filename = trim((string) preg_replace('/[\x00-\x1F\x7F\/\\\\]+/u', '_', $filename));
        if (trim($filename, ' _.') === '') {
            $filename = 'document.docx';
        }
        $fallback = (string) preg_replace('/[^\x20-\x7E]|[%"]/u', '_', $filename);

        return HeaderUtils::makeDisposition(ResponseHeaderBag::DISPOSITION_ATTACHMENT, $filename, $fallback);
    }
}
