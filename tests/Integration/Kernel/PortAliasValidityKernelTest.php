<?php

declare(strict_types=1);

namespace Nowo\HtmlToWordBundle\Tests\Integration\Kernel;

use Nowo\HtmlToWordBundle\Export\DocumentWriterInterface;
use Nowo\HtmlToWordBundle\Export\DocxExporter;
use Nowo\HtmlToWordBundle\Export\ExporterInterface;
use Nowo\HtmlToWordBundle\Http\DocxResponseFactory;
use Nowo\HtmlToWordBundle\Tests\Fixtures\PortAliasKernel;
use Nowo\HtmlToWordBundle\Tests\Fixtures\Ports\DecoratingDocumentWriter;
use Nowo\HtmlToWordBundle\Tests\Fixtures\Ports\DualHostExporter;
use Nowo\HtmlToWordBundle\Tests\Fixtures\Ports\HostDocumentWriter;
use Nowo\HtmlToWordBundle\Tests\Fixtures\Ports\LegacyHostExporter;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use ReflectionProperty;
use Symfony\Bundle\FrameworkBundle\Console\Application;
use Symfony\Component\Console\Tester\CommandTester;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\DependencyInjection\ServiceLocator;
use Symfony\Component\Filesystem\Filesystem;

use function sys_get_temp_dir;
use function uniqid;

/**
 * 1.8.0: `lint:container` (which adds Symfony's `CheckAliasValidityPass`) passes for a host on the new
 * {@see DocumentWriterInterface} only, on the deprecated {@see ExporterInterface} only, on both, and with a decorator of
 * the new port; each alias resolves to a service that implements it, and the bundle's own consumer
 * ({@see DocxResponseFactory}) gets the host service of the new port.
 */
final class PortAliasValidityKernelTest extends TestCase
{
    private string $baseDir;

    protected function setUp(): void
    {
        $this->baseDir = sys_get_temp_dir() . '/htw_port_alias_' . uniqid('', true);
    }

    protected function tearDown(): void
    {
        (new Filesystem())->remove($this->baseDir);
    }

    /**
     * @return iterable<string, array{string, class-string, class-string, class-string}>
     */
    public static function hostPorts(): iterable
    {
        // variant, DocumentWriterInterface service, ExporterInterface service, writer of DocxResponseFactory
        yield 'bundle defaults' => ['none', DocxExporter::class, DocxExporter::class, DocxExporter::class];
        yield 'new port only' => ['new', HostDocumentWriter::class, DocxExporter::class, HostDocumentWriter::class];
        yield 'deprecated port only' => ['legacy', DocxExporter::class, LegacyHostExporter::class, DocxExporter::class];
        yield 'both ports' => ['both', DualHostExporter::class, DualHostExporter::class, DualHostExporter::class];
        yield 'decorator of the new port' => ['decorator', DecoratingDocumentWriter::class, DocxExporter::class, DecoratingDocumentWriter::class];
    }

    /**
     * @param class-string $writer
     * @param class-string $exporter
     * @param class-string $factoryWriter
     */
    #[DataProvider('hostPorts')]
    public function testLintContainerPassesAndConsumersUseTheHostService(string $variant, string $writer, string $exporter, string $factoryWriter): void
    {
        $kernel = new PortAliasKernel($this->baseDir, $variant);
        $kernel->boot();

        $application = new Application($kernel);
        $application->setAutoExit(false);
        $tester = new CommandTester($application->find('lint:container'));

        self::assertSame(0, $tester->execute([]), $tester->getDisplay());
        self::assertStringContainsString('linted successfully', $tester->getDisplay());

        /** @var ContainerInterface $services */
        $services = $kernel->getContainer()->get('test.service_container');

        $ports = $services->get('test.htw_ports');
        self::assertInstanceOf(ServiceLocator::class, $ports);
        self::assertInstanceOf($writer, $ports->get(DocumentWriterInterface::class));
        self::assertInstanceOf($writer, $services->get(DocumentWriterInterface::class));
        self::assertInstanceOf($exporter, $ports->get(ExporterInterface::class));

        $factory = $services->get(DocxResponseFactory::class);
        self::assertInstanceOf(DocxResponseFactory::class, $factory);
        self::assertInstanceOf($factoryWriter, (new ReflectionProperty($factory, 'writer'))->getValue($factory));

        if ($variant === 'decorator') {
            $decorator = $services->get(DocumentWriterInterface::class);
            self::assertInstanceOf(DecoratingDocumentWriter::class, $decorator);
            self::assertInstanceOf(DocxExporter::class, $decorator->inner);
        }

        $kernel->shutdown();
    }
}
