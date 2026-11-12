<?php

declare(strict_types=1);

namespace Nowo\HtmlToWordBundle\Tests\Fixtures;

use Nowo\HtmlToWordBundle\Export\DocumentWriterInterface;
use Nowo\HtmlToWordBundle\Export\ExporterInterface;
use Nowo\HtmlToWordBundle\HtmlToWordBundle;
use Nowo\HtmlToWordBundle\Tests\Fixtures\Ports\DecoratingDocumentWriter;
use Nowo\HtmlToWordBundle\Tests\Fixtures\Ports\DualHostExporter;
use Nowo\HtmlToWordBundle\Tests\Fixtures\Ports\HostDocumentWriter;
use Nowo\HtmlToWordBundle\Tests\Fixtures\Ports\LegacyHostExporter;
use Symfony\Bundle\FrameworkBundle\FrameworkBundle;
use Symfony\Bundle\FrameworkBundle\Kernel\MicroKernelTrait;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;
use Symfony\Component\DependencyInjection\Loader\Configurator\ServicesConfigurator;
use Symfony\Component\DependencyInjection\ServiceLocator;
use Symfony\Component\HttpKernel\Kernel;

use function Symfony\Component\DependencyInjection\Loader\Configurator\service;

/**
 * Host kernel whose services re-point the bundle's output ports the way a host would in its own services.yaml:
 * `new` (the 1.8 port only), `legacy` (the deprecated port only), `both`, `decorator` (an `#[AsDecorator]`-style
 * decorator of the 1.8 port) or `none` (bundle defaults).
 */
final class PortAliasKernel extends Kernel
{
    use MicroKernelTrait;

    public function __construct(
        private readonly string $baseDir,
        private readonly string $variant,
    ) {
        parent::__construct('test', false);
    }

    public function registerBundles(): iterable
    {
        yield new FrameworkBundle();
        yield new HtmlToWordBundle();
    }

    public function getCacheDir(): string
    {
        return $this->baseDir . '/cache/' . $this->variant;
    }

    public function getLogDir(): string
    {
        return $this->baseDir . '/log';
    }

    protected function configureContainer(ContainerConfigurator $container): void
    {
        $container->import(__DIR__ . '/config/packages/*.yaml');

        $services = $container->services();
        // Test probe: keeps both port aliases (the deprecated one is private and otherwise unused) reachable.
        $services->set('test.htw_ports', ServiceLocator::class)
            ->args([[
                DocumentWriterInterface::class => service(DocumentWriterInterface::class),
                ExporterInterface::class       => service(ExporterInterface::class),
            ]])
            ->tag('container.service_locator')
            ->public();

        match ($this->variant) {
            'new'       => $this->newPortOnly($services),
            'legacy'    => $this->legacyPortOnly($services),
            'both'      => $this->bothPorts($services),
            'decorator' => $services->set(DecoratingDocumentWriter::class)
                ->decorate(DocumentWriterInterface::class)
                ->args([service('.inner')]),
            default => null,
        };
    }

    private function newPortOnly(ServicesConfigurator $services): void
    {
        $services->set(HostDocumentWriter::class);
        $services->alias(DocumentWriterInterface::class, HostDocumentWriter::class);
    }

    private function legacyPortOnly(ServicesConfigurator $services): void
    {
        $services->set(LegacyHostExporter::class);
        $services->alias(ExporterInterface::class, LegacyHostExporter::class);
    }

    private function bothPorts(ServicesConfigurator $services): void
    {
        $services->set(DualHostExporter::class);
        $services->alias(DocumentWriterInterface::class, DualHostExporter::class);
        $services->alias(ExporterInterface::class, DualHostExporter::class);
    }
}
