<?php

declare(strict_types=1);

namespace Nowo\HtmlToWordBundle\Tests\Unit\Security;

use Nowo\HtmlToWordBundle\Security\RemoteImageHostPolicy;
use PHPUnit\Framework\TestCase;

final class RemoteImageHostPolicyTest extends TestCase
{
    public function testEmptyAllowlistDenies(): void
    {
        self::assertFalse(RemoteImageHostPolicy::isAllowed('https://example.com/a.png', []));
    }

    public function testHostSubstringAllows(): void
    {
        self::assertTrue(RemoteImageHostPolicy::isAllowed('https://cdn.example.com/a.png', ['example.com']));
    }
}
