<?php

declare(strict_types=1);

namespace Nowo\HtmlToWordBundle\Tests\Integration;

use Nowo\HtmlToWordBundle\Builder\TemporaryImageFiles;
use Nowo\HtmlToWordBundle\Converter\HtmlToWordConverter;
use Nowo\HtmlToWordBundle\Export\DocxExporter;
use Nowo\HtmlToWordBundle\Parser\RemoteHttpImageInliner;
use Nowo\HtmlToWordBundle\Tests\Fixtures\AppKernel;
use PHPUnit\Framework\Attributes\PreserveGlobalState;
use PHPUnit\Framework\Attributes\RunInSeparateProcess;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\KernelInterface;
use Symfony\Component\HttpKernel\TerminableInterface;
use Symfony\Contracts\Service\ResetInterface;

/**
 * Same container across consecutive requests without services_resetter (FrankenPHP worker mode).
 */
final class WorkerModeTemporaryFilesTest extends KernelTestCase
{
    private const PNG_1X1 = 'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mP8z8BQDwAEhQGAhKmMIQAAAABJRU5ErkJggg==';

    protected static function getKernelClass(): string
    {
        return AppKernel::class;
    }

    protected function tearDown(): void
    {
        self::ensureKernelShutdown();
        parent::tearDown();
    }

    #[RunInSeparateProcess]
    #[PreserveGlobalState(false)]
    public function testConsecutiveRequestsWithoutResetLeaveNoTempImages(): void
    {
        $kernel                           = self::bootKernel();
        [$converter, $exporter, $tracker] = $this->services();
        $html                             = '<img src="data:image/png;base64,' . self::PNG_1X1 . '"/>';

        // Request 1: convert + export.
        $doc1   = $converter->convert($html);
        $files1 = $doc1->temporaryFiles();
        self::assertCount(1, $files1);
        self::assertFileExists($files1[0]);

        $out = sys_get_temp_dir() . '/htw_worker_' . uniqid('', true) . '.docx';
        try {
            $exporter->toFile($doc1, $out);
            self::assertGreaterThan(1000, filesize($out) ?: 0);
        } finally {
            @unlink($out);
        }
        self::assertFileDoesNotExist($files1[0]);
        self::assertSame([], $tracker->pending());
        $this->terminate($kernel);

        // Request 2: convert only (never exported); kernel.terminate still runs.
        $doc2   = $converter->convert($html);
        $files2 = $doc2->temporaryFiles();
        self::assertCount(1, $files2);
        self::assertNotSame($files1[0], $files2[0]);
        self::assertFileExists($files2[0]);

        $this->terminate($kernel);

        self::assertFileDoesNotExist($files2[0]);
        self::assertSame([], $tracker->pending());
    }

    #[RunInSeparateProcess]
    #[PreserveGlobalState(false)]
    public function testTwoDocumentsInOneRequestKeepTheirOwnImages(): void
    {
        self::bootKernel();
        [$converter, $exporter] = $this->services();
        $html                   = '<img src="data:image/png;base64,' . self::PNG_1X1 . '"/>';

        $docA = $converter->convert($html);
        $docB = $converter->convert($html);

        $response = $exporter->toStreamResponse($docA);
        ob_start();
        $response->sendContent();
        self::assertStringStartsWith('PK', (string) ob_get_clean());

        self::assertFileDoesNotExist($docA->temporaryFiles()[0]);
        self::assertFileExists($docB->temporaryFiles()[0]);

        $binary = $exporter->toBinaryResponse($docB);
        self::assertFileDoesNotExist($docB->temporaryFiles()[0]);
        @unlink($binary->getFile()->getPathname());
    }

    #[RunInSeparateProcess]
    #[PreserveGlobalState(false)]
    public function testTrackerIsSharedAndResettable(): void
    {
        self::bootKernel();
        $container = self::getContainer();

        /** @var TemporaryImageFiles $tracker */
        $tracker = $container->get(TemporaryImageFiles::class);
        $file    = tempnam(sys_get_temp_dir(), 'htw_b64_');
        self::assertNotFalse($file);
        $tracker->register($file);

        $resetter = $container->get('services_resetter');
        self::assertInstanceOf(ResetInterface::class, $resetter);
        $resetter->reset();

        self::assertFileDoesNotExist($file);
    }

    /**
     * @return array{0: HtmlToWordConverter, 1: DocxExporter, 2: TemporaryImageFiles}
     */
    private function services(): array
    {
        $container = self::getContainer();

        /** @var HtmlToWordConverter $converter */
        $converter = $container->get(HtmlToWordConverter::class);
        /** @var RemoteHttpImageInliner $inliner */
        $inliner = $container->get(RemoteHttpImageInliner::class);
        /** @var TemporaryImageFiles $tracker */
        $tracker = $container->get(TemporaryImageFiles::class);

        return [$converter, new DocxExporter($inliner), $tracker];
    }

    private function terminate(KernelInterface $kernel): void
    {
        self::assertInstanceOf(TerminableInterface::class, $kernel);
        $kernel->terminate(new Request(), new Response());
    }
}
