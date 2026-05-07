<?php

declare(strict_types=1);

namespace Nowo\HtmlToWordBundle\Engine;

use Nowo\HtmlToWordBundle\Builder\WordDocumentBuilder;
use Nowo\HtmlToWordBundle\Config\ResolvedConfig;
use Nowo\HtmlToWordBundle\Model\WordDocument;
use PhpOffice\PhpWord\PhpWord;

/**
 * HTML → Word using PHPWord (default engine).
 *
 * @author Héctor Franco Aceituno <hectorfranco@nowo.tech>
 */
final readonly class PhpWordEngine implements WordEngineInterface
{
    public const NAME = 'phpword';

    public function __construct(
        private WordDocumentBuilder $builder,
    ) {
    }

    public function getName(): string
    {
        return self::NAME;
    }

    public function requiredPackages(): array
    {
        return ['phpoffice/phpword'];
    }

    public function isAvailable(): bool
    {
        return class_exists(PhpWord::class);
    }

    public function build(string $html, ResolvedConfig $config): WordDocument
    {
        return $this->builder->build($html, $config);
    }
}
