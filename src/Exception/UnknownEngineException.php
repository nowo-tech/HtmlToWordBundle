<?php

declare(strict_types=1);

namespace Nowo\HtmlToWordBundle\Exception;

use InvalidArgumentException;

use function implode;
use function sprintf;

/**
 * @author Héctor Franco Aceituno <hectorfranco@nowo.tech>
 */
final class UnknownEngineException extends InvalidArgumentException
{
    /**
     * @param list<string> $registered
     */
    public static function create(string $requested, array $registered): self
    {
        return new self(sprintf(
            'Unknown nowo_html_to_word engine "%s". Registered engines: %s.',
            $requested,
            $registered === [] ? '(none)' : implode(', ', $registered),
        ));
    }
}
