<?php

declare(strict_types=1);

namespace Nowo\HtmlToWordBundle\Tests\Integration;

use Nowo\HtmlToWordBundle\Converter\HtmlToWordConverter;
use Nowo\HtmlToWordBundle\Export\DocxExporter;
use Nowo\HtmlToWordBundle\Parser\RemoteHttpImageInliner;
use Nowo\HtmlToWordBundle\Tests\Fixtures\AppKernel;
use PHPUnit\Framework\Attributes\PreserveGlobalState;
use PHPUnit\Framework\Attributes\RunInSeparateProcess;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Process\Process;
use ZipArchive;

use const PHP_BINARY;

/**
 * Full container pipeline: remote images inlined to tracked temp paths are still embedded, while arbitrary local
 * paths in user HTML are not read.
 */
final class RemoteImagePipelineTest extends KernelTestCase
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
    public function testInlinedRemoteImageIsEmbeddedAndLocalPathIsNot(): void
    {
        $docRoot = sys_get_temp_dir() . '/htw_pipe_' . uniqid('', true);
        mkdir($docRoot);
        file_put_contents($docRoot . '/pixel.png', (string) base64_decode(self::PNG_1X1, true));
        $secret = sys_get_temp_dir() . '/htw_secret_' . uniqid('', true) . '.png';
        file_put_contents($secret, (string) base64_decode(self::PNG_1X1, true));

        $port   = random_int(29000, 29999);
        $server = new Process([PHP_BINARY, '-S', '127.0.0.1:' . $port, '-t', $docRoot]);
        $server->start();
        $out = sys_get_temp_dir() . '/htw_pipe_' . uniqid('', true) . '.docx';
        try {
            for ($i = 0; $i < 50 && @file_get_contents('http://127.0.0.1:' . $port . '/pixel.png') === false; ++$i) {
                usleep(50_000);
            }

            self::bootKernel();
            /** @var HtmlToWordConverter $converter */
            $converter = self::getContainer()->get(HtmlToWordConverter::class);
            /** @var RemoteHttpImageInliner $inliner */
            $inliner  = self::getContainer()->get(RemoteHttpImageInliner::class);
            $exporter = new DocxExporter($inliner);
            $options  = ['images' => [
                'resolve_remote'               => true,
                'remote_host_allowlist'        => ['127.0.0.1'],
                'remote_allow_private_network' => true,
            ]];

            $exporter->toFile($converter->convertWithOptions('<p><img src="http://127.0.0.1:' . $port . '/pixel.png"/></p>', $options), $out);
            self::assertSame(1, $this->mediaCount($out));

            $exporter->toFile($converter->convertWithOptions('<p><img src="' . $secret . '"/></p>', $options), $out);
            self::assertSame(0, $this->mediaCount($out));
        } finally {
            $server->stop(1);
            @unlink($out);
            @unlink($secret);
            @unlink($docRoot . '/pixel.png');
            @rmdir($docRoot);
        }
    }

    private function mediaCount(string $docx): int
    {
        $zip = new ZipArchive();
        self::assertTrue($zip->open($docx));
        $count = 0;
        for ($i = 0; $i < $zip->numFiles; ++$i) {
            if (str_starts_with((string) $zip->getNameIndex($i), 'word/media/')) {
                ++$count;
            }
        }
        $zip->close();

        return $count;
    }
}
