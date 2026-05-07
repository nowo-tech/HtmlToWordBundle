<?php

declare(strict_types=1);

namespace Nowo\HtmlToWordBundle\Exception;

use InvalidArgumentException;

final class InvalidProfileException extends InvalidArgumentException implements HtmlToWordExceptionInterface
{
}
