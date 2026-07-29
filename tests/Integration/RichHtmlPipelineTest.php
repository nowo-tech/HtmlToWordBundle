<?php

declare(strict_types=1);

namespace Nowo\HtmlToWordBundle\Tests\Integration;

use Nowo\HtmlToWordBundle\Converter\HtmlToWordConverter;
use Nowo\HtmlToWordBundle\Tests\Fixtures\AppKernel;
use PhpOffice\PhpWord\IOFactory;
use PhpOffice\PhpWord\PhpWord;
use PHPUnit\Framework\Attributes\PreserveGlobalState;
use PHPUnit\Framework\Attributes\RunInSeparateProcess;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

/**
 * Exercises the HTML pipeline (tables, lists, inline styles, images, headings, etc.).
 */
final class RichHtmlPipelineTest extends KernelTestCase
{
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
    public function testRichHtmlConvertsToWord(): void
    {
        self::bootKernel();
        $converter = self::getContainer()->get(HtmlToWordConverter::class);
        self::assertInstanceOf(HtmlToWordConverter::class, $converter);

        $png1x1 = 'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mP8z8BQDwAEhQGAhKmMIQAAAABJRU5ErkJggg==';

        $html = <<<HTML
Plain text node before block.
<table></table>
<table>
  <caption>Title</caption>
  <thead>
    <tr>
      <th style="background-color:#ff0000;vertical-align:top">H1</th>
      <th colspan="2" style="vertical-align:middle">Wide</th>
    </tr>
  </thead>
  <tbody>
    <tr><td style="vertical-align:bottom">a</td><td>b</td><td>c</td></tr>
  </tbody>
  <tfoot><tr><td>f1</td><td>f2</td><td>f3</td></tr></tfoot>
</table>
<table><tr><td>direct</td></tr></table>
<ul><li>One<ul><li>Nested</li></ul></li><li>Two</li></ul>
<ol><li>First</li><li>Second</li></ol>
<blockquote>Bq</blockquote>
<div class="box"><p>In div</p></div>
<pre>monospace</pre>
<hr/>
<h1>Eins</h1><h2>Zwei</h2><h3>Drei</h3><h4>Vier</h4><h5>Fünf</h5><h6>Sechs</h6>
<img src="data:image/png;base64,{$png1x1}" width="40" height="40" style="width:80px"/>
<img src="not-a-valid-url" alt="x"/>
<p><a>Link without href</a></p>
<p>Line <strong>bold</strong> <em>italic</em> <u>under</u> <s>strike</s> <sup>up</sup> <sub>dn</sub><br/>
<a href="https://example.com">link</a> <span style="color:red">red</span> <code>code</code></p>
<section><p>Section</p></section>
<article><p>Article</p></article>
<main><p>Main</p></main>
<nav><p>Nav</p></nav>
<figure><p>Figure caption area</p></figure>
<div data-page-break="1"></div>
<p>After page break hint</p>
<div class="foo page-break bar"><p>Inside page-break class</p></div>
<small>Small</small> <mark>Mark</mark> <del>Del</del>
<p><img src=":::bad:::"/><strong><em>nested</em></strong></p>
<p><kbd>kbd</kbd> <address>addr</address></p>
<p><font color="#112233">Legacy font</font></p>
<p><strong><span>Inner span</span></strong></p>
HTML;

        $doc = $converter->convert($html);
        $tmp = sys_get_temp_dir() . '/htw_rich_' . uniqid() . '.docx';

        try {
            $writer = IOFactory::createWriter($doc->phpWord(), 'Word2007');
            $writer->save($tmp);
            self::assertFileExists($tmp);
            self::assertGreaterThan(4000, filesize($tmp) ?: 0);
        } finally {
            @unlink($tmp);
        }
    }

    #[RunInSeparateProcess]
    #[PreserveGlobalState(false)]
    public function testConvertWithOptionsChangesPageLayout(): void
    {
        self::bootKernel();
        /** @var HtmlToWordConverter $converter */
        $converter = self::getContainer()->get(HtmlToWordConverter::class);

        $doc = $converter->convertWithOptions(
            '<p>Letter landscape</p>',
            [
                'page' => [
                    'size'        => 'LETTER',
                    'orientation' => 'landscape',
                    'margins'     => ['top' => 100, 'right' => 100, 'bottom' => 100, 'left' => 100],
                ],
            ],
        );

        $tmp = sys_get_temp_dir() . '/htw_opts_' . uniqid() . '.docx';
        try {
            $writer = IOFactory::createWriter($doc->phpWord(), 'Word2007');
            $writer->save($tmp);
            self::assertGreaterThan(2000, filesize($tmp) ?: 0);
        } finally {
            @unlink($tmp);
        }
    }

    #[RunInSeparateProcess]
    #[PreserveGlobalState(false)]
    public function testCustomPageSize(): void
    {
        self::bootKernel();
        /** @var HtmlToWordConverter $converter */
        $converter = self::getContainer()->get(HtmlToWordConverter::class);

        $doc = $converter->convertWithOptions(
            '<p>custom</p>',
            [
                'page' => [
                    'size'          => 'CUSTOM',
                    'custom_width'  => 10000,
                    'custom_height' => 12000,
                ],
            ],
        );

        self::assertSame('phpword', $doc->engine());
        self::assertInstanceOf(PhpWord::class, $doc->native());
    }

    #[RunInSeparateProcess]
    #[PreserveGlobalState(false)]
    public function testTableWithoutRepeatHeaderRow(): void
    {
        self::bootKernel();
        /** @var HtmlToWordConverter $converter */
        $converter = self::getContainer()->get(HtmlToWordConverter::class);

        $html = '<table><thead><tr><th>H</th></tr></thead><tbody><tr><td>d</td></tr></tbody></table>';
        $doc  = $converter->convertWithOptions($html, [
            'tables' => ['repeat_header_row' => false],
        ]);

        $tmp = sys_get_temp_dir() . '/htw_tbl_' . uniqid() . '.docx';
        try {
            $writer = IOFactory::createWriter($doc->phpWord(), 'Word2007');
            $writer->save($tmp);
            self::assertGreaterThan(2000, filesize($tmp) ?: 0);
        } finally {
            @unlink($tmp);
        }
    }
}
