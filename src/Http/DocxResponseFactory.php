<?php

declare(strict_types=1);

namespace Nowo\HtmlToWordBundle\Http;

use Nowo\HtmlToWordBundle\Exception\ExportException;
use Nowo\HtmlToWordBundle\Export\DocumentWriterInterface;
use Nowo\HtmlToWordBundle\Export\DocxExporter;
use Nowo\HtmlToWordBundle\Export\ExporterInterface;
use Nowo\HtmlToWordBundle\Model\WordDocument;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Throwable;

use function fclose;
use function fopen;
use function sys_get_temp_dir;
use function tempnam;
use function unlink;

/**
 * HTTP edge for DOCX downloads (REQ-ARCH-001 rule 3): turns a {@see WordDocument} into a Symfony response by writing it
 * through the framework-free {@see DocumentWriterInterface}. Use it in controllers; domain and application code depend
 * on {@see DocumentWriterInterface} only.
 *
 * When the host aliases or decorates {@see DocumentWriterInterface}, this factory uses the host service.
 *
 * Since 1.8.0; replaces {@see ExporterInterface::toStreamResponse()} and
 * {@see ExporterInterface::toBinaryResponse()}.
 *
 * @author Héctor Franco Aceituno <hectorfranco@nowo.tech>
 */
final readonly class DocxResponseFactory
{
    public function __construct(
        private DocumentWriterInterface $writer,
    ) {
    }

    /**
     * Sends the DOCX as a streamed response. Nothing is written when this method returns: when the response is sent,
     * the writer writes the DOCX to a temporary file and then copies it to the output (with the default
     * {@see DocxExporter}). A write error therefore happens after the headers are
     * sent and cannot become an error response; use {@see binaryFile()} when it must.
     */
    public function streamed(WordDocument $document): StreamedResponse
    {
        $writer = $this->writer;

        return new StreamedResponse(
            static function () use ($writer, $document): void {
                $output = fopen('php://output', 'w');
                // @codeCoverageIgnoreStart
                if ($output === false) {
                    throw new ExportException('Could not open the output stream for the DOCX download.');
                }
                // @codeCoverageIgnoreEnd
                try {
                    $writer->toStream($document, $output);
                } finally {
                    fclose($output);
                }
            },
            200,
            DocxDownloadHeaders::for($document->suggestedFilename()),
        );
    }

    /**
     * Writes the DOCX to a temporary file now and returns a response that sends it and deletes it afterwards.
     *
     * @throws ExportException when the document cannot be written
     */
    public function binaryFile(WordDocument $document): BinaryFileResponse
    {
        $tmp = tempnam(sys_get_temp_dir(), 'html_to_word_');
        // @codeCoverageIgnoreStart
        if ($tmp === false) {
            throw new ExportException('Could not create temporary file for DOCX.');
        }
        // @codeCoverageIgnoreEnd

        try {
            $this->writer->toFile($document, $tmp);
        } catch (Throwable $e) {
            @unlink($tmp);

            throw $e;
        }

        $response = new BinaryFileResponse($tmp, 200, DocxDownloadHeaders::for($document->suggestedFilename()));
        $response->deleteFileAfterSend(true);

        return $response;
    }
}
