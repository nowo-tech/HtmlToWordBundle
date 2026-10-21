<?php

declare(strict_types=1);

namespace Nowo\HtmlToWordBundle\Export;

use League\Flysystem\FilesystemOperator;
use Nowo\HtmlToWordBundle\Engine\PhpWordEngine;
use Nowo\HtmlToWordBundle\Exception\ExportException;
use Nowo\HtmlToWordBundle\Model\WordDocument;
use Nowo\HtmlToWordBundle\Parser\RemoteHttpImageInliner;
use PhpOffice\PhpWord\IOFactory;
use PhpOffice\PhpWord\PhpWord;
use Psr\Container\ContainerInterface;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\HeaderUtils;
use Symfony\Component\HttpFoundation\ResponseHeaderBag;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Throwable;

use function is_string;
use function preg_replace;
use function sprintf;
use function trim;

/**
 * {@code league/flysystem} is optional: {@see toFlysystem()} only works when it is installed and either a
 * profile sets {@code export.flysystem_adapter} (resolved through {@code $flysystemAdapters}) or a
 * {@code $flysystem} operator is injected.
 *
 * @author Héctor Franco Aceituno <hectorfranco@nowo.tech>
 */
final readonly class DocxExporter implements ExporterInterface
{
    public function __construct(
        private RemoteHttpImageInliner $remoteHttpImageInliner,
        private ?FilesystemOperator $flysystem = null,
        private ?ContainerInterface $flysystemAdapters = null,
    ) {
    }

    public function toStreamResponse(WordDocument $document): StreamedResponse
    {
        $filename = $document->suggestedFilename();
        $writer   = IOFactory::createWriter($this->requirePhpWord($document), 'Word2007');
        $inliner  = $this->remoteHttpImageInliner;

        return new StreamedResponse(
            static function () use ($writer, $inliner, $document): void {
                try {
                    $writer->save('php://output');
                    // Writer failures while streaming are rare in CI; catch kept for production safety.
                    // @codeCoverageIgnoreStart
                } catch (Throwable $e) {
                    throw new ExportException('Failed to stream DOCX: ' . $e->getMessage(), 0, $e);
                    // @codeCoverageIgnoreEnd
                } finally {
                    $inliner->releaseTemporaryFiles($document);
                }
            },
            200,
            $this->responseHeaders($filename),
        );
    }

    public function toBinaryResponse(WordDocument $document): BinaryFileResponse
    {
        $filename = $document->suggestedFilename();
        $tmp      = tempnam(sys_get_temp_dir(), 'html_to_word_');
        // @codeCoverageIgnoreStart
        if ($tmp === false) {
            throw new ExportException('Could not create temporary file for DOCX.');
        }
        // @codeCoverageIgnoreEnd

        try {
            $writer = IOFactory::createWriter($this->requirePhpWord($document), 'Word2007');
            $writer->save($tmp);
        } catch (Throwable $e) {
            // @codeCoverageIgnoreStart
            @unlink($tmp);
            throw new ExportException('Failed to write DOCX to temporary file: ' . $e->getMessage(), 0, $e);
            // @codeCoverageIgnoreEnd
        } finally {
            $this->remoteHttpImageInliner->releaseTemporaryFiles($document);
        }

        $response = new BinaryFileResponse($tmp);
        $response->headers->set('Content-Type', 'application/vnd.openxmlformats-officedocument.wordprocessingml.document');
        $response->headers->set('Content-Disposition', $this->contentDisposition($filename));
        $response->deleteFileAfterSend(true);

        return $response;
    }

    public function toFile(WordDocument $document, string $path): void
    {
        try {
            $writer = IOFactory::createWriter($this->requirePhpWord($document), 'Word2007');
            $writer->save($path);
        } catch (Throwable $e) {
            // @codeCoverageIgnoreStart
            throw new ExportException(sprintf('Failed to save DOCX to "%s": %s', $path, $e->getMessage()), 0, $e);
            // @codeCoverageIgnoreEnd
        } finally {
            $this->remoteHttpImageInliner->releaseTemporaryFiles($document);
        }
    }

    public function toFlysystem(WordDocument $document, string $remotePath): void
    {
        $filesystem = $this->resolveFilesystem($document);

        $tmp = tempnam(sys_get_temp_dir(), 'html_to_word_fs_');
        // @codeCoverageIgnoreStart
        if ($tmp === false) {
            throw new ExportException('Could not create temporary file for Flysystem upload.');
        }
        // @codeCoverageIgnoreEnd

        try {
            $this->toFile($document, $tmp);
            $stream = fopen($tmp, 'r');
            // @codeCoverageIgnoreStart
            if ($stream === false) {
                throw new ExportException('Could not open temporary DOCX for Flysystem upload.');
            }
            // @codeCoverageIgnoreEnd
            try {
                $filesystem->writeStream($remotePath, $stream);
            } finally {
                fclose($stream);
            }
        } finally {
            @unlink($tmp);
        }
    }

    private function resolveFilesystem(WordDocument $document): FilesystemOperator
    {
        $id = $document->resolvedConfig()->get('export.flysystem_adapter');
        if (!is_string($id) || trim($id) === '') {
            if (!$this->flysystem instanceof FilesystemOperator) {
                throw new ExportException('Flysystem adapter is not configured for HtmlToWordBundle exporter: install league/flysystem and set export.flysystem_adapter (or inject $flysystem).');
            }

            return $this->flysystem;
        }

        $id = trim($id);
        if (!$this->flysystemAdapters instanceof ContainerInterface || !$this->flysystemAdapters->has($id)) {
            throw new ExportException(sprintf('Flysystem adapter "%s" is not available: declare it as export.flysystem_adapter in a nowo_html_to_word profile.', $id));
        }

        $filesystem = $this->flysystemAdapters->get($id);
        if (!$filesystem instanceof FilesystemOperator) {
            throw new ExportException(sprintf('Flysystem adapter "%s" must implement League\\Flysystem\\FilesystemOperator (is league/flysystem installed?).', $id));
        }

        return $filesystem;
    }

    private function requirePhpWord(WordDocument $document): PhpWord
    {
        if ($document->engine() !== PhpWordEngine::NAME) {
            throw new ExportException(sprintf('DocxExporter only supports engine "%s"; this document was built with "%s".', PhpWordEngine::NAME, $document->engine()));
        }

        return $document->phpWord();
    }

    /**
     * @return array<string, string>
     */
    private function responseHeaders(string $filename): array
    {
        return [
            'Content-Type'        => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
            'Content-Disposition' => $this->contentDisposition($filename),
        ];
    }

    /**
     * Builds an RFC 6266 attachment header (quoted ASCII fallback + filename* UTF-8) via {@see HeaderUtils::makeDisposition()},
     * so quotes, CR/LF, path separators or non-ASCII characters in export.filename cannot break or inject headers.
     */
    private function contentDisposition(string $filename): string
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
