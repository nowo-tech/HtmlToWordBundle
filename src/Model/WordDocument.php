<?php

declare(strict_types=1);

namespace Nowo\HtmlToWordBundle\Model;

use LogicException;
use Nowo\HtmlToWordBundle\Config\ResolvedConfig;
use PhpOffice\PhpWord\PhpWord;

use function sprintf;

/**
 * Value object wrapping the native engine document (e.g. {@see PhpWord}) + resolved options.
 *
 * @author Héctor Franco Aceituno <hectorfranco@nowo.tech>
 */
final readonly class WordDocument
{
    public function __construct(
        private object $nativeDocument,
        private ResolvedConfig $config,
        private string $engineName,
    ) {
    }

    public function engine(): string
    {
        return $this->engineName;
    }

    /**
     * Opaque handle for the active conversion backend (type depends on {@see engine()}).
     */
    public function native(): object
    {
        return $this->nativeDocument;
    }

    public function phpWord(): PhpWord
    {
        if ($this->engineName !== 'phpword' || !$this->nativeDocument instanceof PhpWord) {
            throw new LogicException(sprintf('WordDocument holds engine "%s"; phpWord() is only valid when engine is "phpword" and the native document is PHPWord.', $this->engineName));
        }

        return $this->nativeDocument;
    }

    public function resolvedConfig(): ResolvedConfig
    {
        return $this->config;
    }

    public function suggestedFilename(): string
    {
        return (string) $this->config->get('export.filename', 'document.docx');
    }
}
