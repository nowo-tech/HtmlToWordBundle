<?php

declare(strict_types=1);

namespace Nowo\HtmlToWordBundle\Exception;

use RuntimeException;

final class ExportException extends RuntimeException implements HtmlToWordExceptionInterface
{
}
