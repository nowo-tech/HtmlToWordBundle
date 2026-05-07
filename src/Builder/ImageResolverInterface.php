<?php

declare(strict_types=1);

namespace Nowo\HtmlToWordBundle\Builder;

use Nowo\HtmlToWordBundle\Config\ResolvedConfig;
use Nowo\HtmlToWordBundle\Exception\ImageResolveException;

/**
 * Resolves {@code <img src>} to a local path for PHPWord (tests may mock this).
 *
 * @author Héctor Franco Aceituno <hectorfranco@nowo.tech>
 */
interface ImageResolverInterface
{
    /**
     * @throws ImageResolveException
     */
    public function resolveToTempPath(string $src, ResolvedConfig $config): string;
}
