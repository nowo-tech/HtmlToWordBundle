<?php

declare(strict_types=1);

namespace Nowo\HtmlToWordBundle\Tests\Integration;

use Nowo\HtmlToWordBundle\Converter\HtmlToWordConverter;
use Nowo\HtmlToWordBundle\Exception\UnsupportedElementException;
use Nowo\HtmlToWordBundle\Tests\Fixtures\StrictAppKernel;
use PHPUnit\Framework\Attributes\PreserveGlobalState;
use PHPUnit\Framework\Attributes\RunInSeparateProcess;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

final class StrictModeUnsupportedTagTest extends KernelTestCase
{
    protected static function getKernelClass(): string
    {
        return StrictAppKernel::class;
    }

    protected function tearDown(): void
    {
        self::ensureKernelShutdown();
        parent::tearDown();
    }

    #[RunInSeparateProcess]
    #[PreserveGlobalState(false)]
    public function testUnknownTagThrowsWhenStrict(): void
    {
        self::bootKernel();
        /** @var HtmlToWordConverter $converter */
        $converter = self::getContainer()->get(HtmlToWordConverter::class);

        $this->expectException(UnsupportedElementException::class);
        $converter->convert('<video src="x"></video>');
    }
}
