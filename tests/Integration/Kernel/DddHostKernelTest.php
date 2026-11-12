<?php

declare(strict_types=1);

namespace Nowo\HtmlToWordBundle\Tests\Integration\Kernel;

use Nowo\HtmlToWordBundle\Export\DocumentWriterInterface;
use Nowo\HtmlToWordBundle\Model\WordDocument;
use Nowo\HtmlToWordBundle\Tests\Fixtures\DddHost\Domain\ContractDocumentRenderer;
use Nowo\HtmlToWordBundle\Tests\Fixtures\DddHost\Infrastructure\HtmlToWordContractRenderer;
use Nowo\HtmlToWordBundle\Tests\Fixtures\DddHostKernel;
use PHPUnit\Framework\TestCase;
use ReflectionClass;
use ReflectionMethod;
use ReflectionNamedType;
use Symfony\Component\Filesystem\Filesystem;
use ZipArchive;

use function file_get_contents;
use function file_put_contents;
use function preg_match;
use function sys_get_temp_dir;
use function uniqid;

/**
 * REQ-ARCH-001, DDD host: a host domain port implemented with the bundle's framework-free ports boots, renders a DOCX
 * and none of the files involved (the host adapter and {@see DocumentWriterInterface}) imports a Symfony component,
 * Doctrine or HttpFoundation type.
 */
final class DddHostKernelTest extends TestCase
{
    private string $baseDir;

    protected function setUp(): void
    {
        $this->baseDir = sys_get_temp_dir() . '/htw_ddd_host_' . uniqid('', true);
    }

    protected function tearDown(): void
    {
        (new Filesystem())->remove($this->baseDir);
    }

    public function testHostDomainPortRendersThroughFrameworkFreePorts(): void
    {
        $kernel = new DddHostKernel($this->baseDir);
        $kernel->boot();

        $renderer = $kernel->getContainer()->get(ContractDocumentRenderer::class);
        self::assertInstanceOf(HtmlToWordContractRenderer::class, $renderer);

        $bytes = $renderer->render('contract-42', '<p>DDD host contract</p>');
        self::assertStringStartsWith('PK', $bytes);

        $path = $this->baseDir . '/contract.docx';
        file_put_contents($path, $bytes);
        $zip = new ZipArchive();
        self::assertTrue($zip->open($path));
        self::assertStringContainsString('DDD host contract', (string) $zip->getFromName('word/document.xml'));
        $zip->close();

        $kernel->shutdown();
    }

    public function testFrameworkFreeSurfaceHasNoFrameworkTypes(): void
    {
        foreach ([DocumentWriterInterface::class, HtmlToWordContractRenderer::class, ContractDocumentRenderer::class] as $class) {
            $file = (string) (new ReflectionClass($class))->getFileName();
            self::assertSame(0, preg_match('/^use (Doctrine\\\\|Symfony\\\\Component\\\\)/m', (string) file_get_contents($file)), $file);
        }

        foreach ((new ReflectionClass(DocumentWriterInterface::class))->getMethods() as $method) {
            foreach ($this->types($method) as $type) {
                self::assertContains($type, [WordDocument::class, 'string', 'mixed', 'void'], $method->getName());
            }
        }
    }

    /**
     * @return list<string>
     */
    private function types(ReflectionMethod $method): array
    {
        $types = [];
        foreach ($method->getParameters() as $parameter) {
            $type = $parameter->getType();
            self::assertInstanceOf(ReflectionNamedType::class, $type);
            $types[] = $type->getName();
        }
        $return = $method->getReturnType();
        self::assertInstanceOf(ReflectionNamedType::class, $return);
        $types[] = $return->getName();

        return $types;
    }
}
