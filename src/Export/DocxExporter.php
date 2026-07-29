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
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\ResponseHeaderBag;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Throwable;

use function sprintf;

/**
 * @author Héctor Franco Aceituno <hectorfranco@nowo.tech>
 */
final readonly class DocxExporter implements ExporterInterface
{
    public function __construct(
        private RemoteHttpImageInliner $remoteHttpImageInliner,
        private ?FilesystemOperator $flysystem = null,
    ) {
    }

    public function toStreamResponse(WordDocument $document): StreamedResponse
    {
        $filename = $document->suggestedFilename();
        $writer   = IOFactory::createWriter($this->requirePhpWord($document), 'Word2007');
        $inliner  = $this->remoteHttpImageInliner;

        return new StreamedResponse(
            static function () use ($writer, $inliner): void {
                try {
                    $writer->save('php://output');
                    // Writer failures while streaming are rare in CI; catch kept for production safety.
                    // @codeCoverageIgnoreStart
                } catch (Throwable $e) {
                    throw new ExportException('Failed to stream DOCX: ' . $e->getMessage(), 0, $e);
                    // @codeCoverageIgnoreEnd
                } finally {
                    $inliner->cleanupInlineSession();
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
            $this->remoteHttpImageInliner->cleanupInlineSession();
        }

        $response = new BinaryFileResponse($tmp);
        $response->headers->set('Content-Type', 'application/vnd.openxmlformats-officedocument.wordprocessingml.document');
        $response->setContentDisposition(ResponseHeaderBag::DISPOSITION_ATTACHMENT, $filename);
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
            $this->remoteHttpImageInliner->cleanupInlineSession();
        }
    }

    public function toFlysystem(WordDocument $document, string $remotePath): void
    {
        if (!$this->flysystem instanceof FilesystemOperator) {
            throw new ExportException('Flysystem adapter is not configured for HtmlToWordBundle exporter.');
        }

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
                $this->flysystem->writeStream($remotePath, $stream);
            } finally {
                fclose($stream);
            }
        } finally {
            @unlink($tmp);
        }
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
            'Content-Disposition' => ResponseHeaderBag::DISPOSITION_ATTACHMENT . '; filename="' . $filename . '"',
        ];
    }
}
