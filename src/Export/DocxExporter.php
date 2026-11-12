<?php

declare(strict_types=1);

namespace Nowo\HtmlToWordBundle\Export;

use League\Flysystem\FilesystemOperator;
use Nowo\HtmlToWordBundle\Engine\PhpWordEngine;
use Nowo\HtmlToWordBundle\Exception\ExportException;
use Nowo\HtmlToWordBundle\Http\DocxDownloadHeaders;
use Nowo\HtmlToWordBundle\Http\DocxResponseFactory;
use Nowo\HtmlToWordBundle\Model\WordDocument;
use Nowo\HtmlToWordBundle\Parser\RemoteHttpImageInliner;
use PhpOffice\PhpWord\IOFactory;
use PhpOffice\PhpWord\PhpWord;
use Psr\Container\ContainerInterface;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Throwable;

use function fclose;
use function file_get_contents;
use function filesize;
use function fopen;
use function is_resource;
use function is_string;
use function sprintf;
use function stream_copy_to_stream;
use function sys_get_temp_dir;
use function tempnam;
use function trigger_deprecation;
use function trim;
use function unlink;

/**
 * Default {@see DocumentWriterInterface} (and the deprecated {@see ExporterInterface}) on PHPWord's Word2007 writer.
 *
 * {@code league/flysystem} is optional: {@see toFlysystem()} only works when it is installed and either a
 * profile sets {@code export.flysystem_adapter} (resolved through {@code $flysystemAdapters}) or a
 * {@code $flysystem} operator is injected.
 *
 * @author Héctor Franco Aceituno <hectorfranco@nowo.tech>
 */
final readonly class DocxExporter implements DocumentWriterInterface, ExporterInterface
{
    public function __construct(
        private RemoteHttpImageInliner $remoteHttpImageInliner,
        private ?FilesystemOperator $flysystem = null,
        private ?ContainerInterface $flysystemAdapters = null,
    ) {
    }

    public function toBytes(WordDocument $document): string
    {
        $tmp = $this->temporaryPath('html_to_word_bytes_');

        try {
            $this->toFile($document, $tmp);
            $bytes = file_get_contents($tmp);
            // @codeCoverageIgnoreStart
            if ($bytes === false) {
                throw new ExportException('Could not read the temporary DOCX.');
            }
            // @codeCoverageIgnoreEnd

            return $bytes;
        } finally {
            @unlink($tmp);
        }
    }

    public function toStream(WordDocument $document, mixed $stream): void
    {
        if (!is_resource($stream)) {
            $this->remoteHttpImageInliner->releaseTemporaryFiles($document);

            throw new ExportException('DocumentWriterInterface::toStream() expects an open, writable stream resource.');
        }

        $tmp = $this->temporaryPath('html_to_word_stream_');

        try {
            $this->toFile($document, $tmp);
            $source = fopen($tmp, 'r');
            // @codeCoverageIgnoreStart
            if ($source === false) {
                throw new ExportException('Could not open the temporary DOCX.');
            }
            // @codeCoverageIgnoreEnd
            try {
                $copied = @stream_copy_to_stream($source, $stream);
                if ($copied === false || $copied !== filesize($tmp)) {
                    throw new ExportException('Could not copy the DOCX into the target stream.');
                }
            } finally {
                fclose($source);
            }
        } finally {
            @unlink($tmp);
        }
    }

    /**
     * @deprecated since 1.8.0, use {@see DocxResponseFactory::streamed()}
     */
    public function toStreamResponse(WordDocument $document): StreamedResponse
    {
        trigger_deprecation('nowo-tech/html-to-word-bundle', '1.8', 'Calling "%s()" is deprecated, use "%s::streamed()" instead.', __METHOD__, DocxResponseFactory::class);

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
            DocxDownloadHeaders::for($filename),
        );
    }

    /**
     * @deprecated since 1.8.0, use {@see DocxResponseFactory::binaryFile()}
     */
    public function toBinaryResponse(WordDocument $document): BinaryFileResponse
    {
        trigger_deprecation('nowo-tech/html-to-word-bundle', '1.8', 'Calling "%s()" is deprecated, use "%s::binaryFile()" instead.', __METHOD__, DocxResponseFactory::class);

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
        $response->headers->set('Content-Type', DocxDownloadHeaders::CONTENT_TYPE);
        $response->headers->set('Content-Disposition', DocxDownloadHeaders::contentDisposition($filename));
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

    private function temporaryPath(string $prefix): string
    {
        $tmp = tempnam(sys_get_temp_dir(), $prefix);
        // @codeCoverageIgnoreStart
        if ($tmp === false) {
            throw new ExportException('Could not create temporary file for DOCX.');
        }
        // @codeCoverageIgnoreEnd

        return $tmp;
    }
}
