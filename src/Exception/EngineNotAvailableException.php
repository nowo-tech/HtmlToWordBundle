<?php

declare(strict_types=1);

namespace Nowo\HtmlToWordBundle\Exception;

use Nowo\HtmlToWordBundle\Engine\WordEngineInterface;
use RuntimeException;

use function implode;
use function sprintf;

/**
 * @author Héctor Franco Aceituno <hectorfranco@nowo.tech>
 */
final class EngineNotAvailableException extends RuntimeException
{
    public static function forEngine(WordEngineInterface $engine): self
    {
        $packages = $engine->requiredPackages();

        return new self(sprintf(
            'Word engine "%s" is not available (missing runtime dependencies). Required Composer package(s): %s',
            $engine->getName(),
            $packages === [] ? '(see engine documentation)' : implode(', ', $packages),
        ));
    }
}
