<?php

declare(strict_types=1);

namespace Nowo\HtmlToWordBundle\Exception;

use RuntimeException;

final class UnsupportedElementException extends RuntimeException implements HtmlToWordExceptionInterface
{
}
