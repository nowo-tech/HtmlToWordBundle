<?php

declare(strict_types=1);

namespace Nowo\HtmlToWordBundle\Engine;

use Nowo\HtmlToWordBundle\Config\ResolvedConfig;
use Nowo\HtmlToWordBundle\Model\WordDocument;

/**
 * Pluggable backend for HTML → Word conversion (e.g. PHPWord).
 *
 * Implementations are Symfony services tagged `html_to_word.engine` (see bundle `services.yaml`).
 *
 * @author Héctor Franco Aceituno <hectorfranco@nowo.tech>
 */
interface WordEngineInterface
{
    /**
     * Stable identifier used in `html_to_word.engine` (e.g. `phpword`).
     */
    public function getName(): string;

    /**
     * Composer package names that must be installed for this engine (for error messages / docs).
     *
     * @return list<string>
     */
    public function requiredPackages(): array;

    /**
     * Whether runtime dependencies (classes/extensions) are present.
     */
    public function isAvailable(): bool;

    public function build(string $html, ResolvedConfig $config): WordDocument;
}
