<?php

declare(strict_types=1);

namespace Nowo\HtmlToWordBundle\Tests\Fixtures;

use Nowo\HtmlToWordBundle\Export\DocumentWriterInterface;
use Nowo\HtmlToWordBundle\HtmlToWordBundle;
use Nowo\HtmlToWordBundle\Tests\Fixtures\DddHost\Domain\ContractDocumentRenderer;
use Nowo\HtmlToWordBundle\Tests\Fixtures\DddHost\Infrastructure\HtmlToWordContractRenderer;
use Symfony\Bundle\FrameworkBundle\FrameworkBundle;
use Symfony\Bundle\FrameworkBundle\Kernel\MicroKernelTrait;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;
use Symfony\Component\HttpKernel\Kernel;

/**
 * DDD / hexagonal host: the domain port {@see ContractDocumentRenderer} is implemented by an autowired adapter that
 * depends on the bundle's framework-free ports only (converter + {@see DocumentWriterInterface}).
 */
final class DddHostKernel extends Kernel
{
    use MicroKernelTrait;

    public function __construct(
        private readonly string $baseDir,
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
        return $this->baseDir . '/cache';
    }

    public function getLogDir(): string
    {
        return $this->baseDir . '/log';
    }

    protected function configureContainer(ContainerConfigurator $container): void
    {
        $container->import(__DIR__ . '/config/packages/*.yaml');

        $services = $container->services();
        $services->set(HtmlToWordContractRenderer::class)->autowire();
        $services->alias(ContractDocumentRenderer::class, HtmlToWordContractRenderer::class)->public();
    }
}
