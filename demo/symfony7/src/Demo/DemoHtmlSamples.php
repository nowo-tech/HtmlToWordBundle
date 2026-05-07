<?php

declare(strict_types=1);

namespace App\Demo;

/**
 * HTML presets + suggested YAML profile + one-line stack note (always phpword engine + HTML5 + bundle transformers).
 */
final class DemoHtmlSamples
{
    /**
     * Replaced at runtime with {@code kernel.project_dir/public/demo/sample.png} so PhpWord receives an absolute path.
     */
    public const DEMO_SAMPLE_IMAGE_PLACEHOLDER = '__DEMO_SAMPLE_IMAGE_PATH__';

    /**
     * Overlay JSON for {@code /custom-config}: Word header (logo + text), footer (text + {@code {PAGE}}).
     * Pass Symfony {@code kernel.project_dir} so {@code header.logo} is an absolute path (JSON cannot use {@code %kernel.project_dir%}).
     *
     * @return array<string, mixed>
     */
    public static function headerFooterDemoOverlay(string $projectDir): array
    {
        return [
            'export' => [
                'filename' => 'demo-cabecera-pie.docx',
            ],
            'header' => [
                'enabled'          => true,
                'text'             => 'HtmlToWordBundle — cabecera (perfil JSON)',
                'logo'             => $projectDir . '/public/demo-assets/demo-logo.png',
                'logo_width'       => 120,
                'show_page_number' => false,
            ],
            'footer' => [
                'enabled'          => true,
                'text'             => 'Demo — ',
                'show_page_number' => true,
            ],
        ];
    }

    /** @var array<string, string> */
    public const PRESETS = [
        'minimal' => <<<'HTML'
<p>This is the minimal example: a single paragraph with <strong>bold</strong> and <em>italic</em>.</p>
HTML,
        'articulo' => <<<'HTML'
<h1>Technical report</h1>
<p class="lead">Short introduction with inline elements: <strong>strong</strong>, <em>emphasis</em>,
<code>code</code>, and a <a href="#">link</a> (may export as plain text).</p>

<h2>Sample image (local file)</h2>
<p>Raster from <code>public/demo/sample.png</code>. At runtime the demo replaces the <code>src</code> placeholder with
<code>kernel.project_dir</code> + <code>/public/demo/sample.png</code> (absolute path). The <code>&lt;img&gt;</code> is a
<strong>block</strong> sibling of paragraphs so PhpWord uses the block image transformer (inline <code>&lt;p&gt;&lt;img&gt;</code>
is less reliable in PhpWord).</p>
<img src="__DEMO_SAMPLE_IMAGE_PATH__" alt="HtmlToWord demo sample" width="200" height="120" />

<h2>Goals</h2>
<ul>
  <li>Validate heading styles and unordered lists.</li>
  <li>Check indentation and spacing between blocks.</li>
</ul>

<h3>Numbered detail</h3>
<ol>
  <li>First item</li>
  <li>Second item with inner <strong>markup</strong></li>
  <li>Third item</li>
</ol>

<h2>Quote</h2>
<blockquote>
  <p><code>blockquote</code> blocks should look distinct from the main body.</p>
</blockquote>

<hr>

<h2>Body text</h2>
<p>Lorem ipsum dolor sit amet, consectetur adipiscing elit. Sed non risus. Suspendisse lectus tortor,
dignissim sit amet, adipiscing nec, ultricies sed, dolor. Cras elementum ultrices diam.</p>
HTML,
        'tablas' => <<<'HTML'
<h1>Advanced tables</h1>
<p>Caption, thead/tbody/tfoot, <code>colspan</code>, cell styles, and repeatable header rows in Word.</p>

<table>
  <caption>Quarterly sales (demo)</caption>
  <thead>
    <tr>
      <th>Region</th>
      <th>Q1</th>
      <th>Q2</th>
      <th>Total</th>
    </tr>
  </thead>
  <tbody>
    <tr>
      <td>North</td>
      <td style="text-align: right;">12,400 €</td>
      <td style="text-align: right;">15,200 €</td>
      <td style="text-align: right;">27,600 €</td>
    </tr>
    <tr>
      <td>South</td>
      <td style="text-align: right;">9,800 €</td>
      <td style="text-align: right;">11,000 €</td>
      <td style="text-align: right;">20,800 €</td>
    </tr>
    <tr>
      <td colspan="3" style="text-align: right;"><strong>Subtotal regions</strong></td>
      <td style="text-align: right;"><strong>48,400 €</strong></td>
    </tr>
  </tbody>
  <tfoot>
    <tr>
      <td colspan="4" style="text-align: center;">Table footer — fictional figures for export testing.</td>
    </tr>
  </tfoot>
</table>

<h2>Compact table with vertical alignment</h2>
<table>
  <thead>
    <tr>
      <th>Concept</th>
      <th>Value</th>
      <th>Notes</th>
    </tr>
  </thead>
  <tbody>
    <tr>
      <td style="vertical-align: top;">Tall row<br>second line</td>
      <td style="vertical-align: middle;">Middle</td>
      <td style="vertical-align: bottom;">Bottom</td>
    </tr>
  </tbody>
</table>
HTML,
        'codigo' => <<<'HTML'
<h1>Code blocks</h1>
<p>Normal paragraph before the preformatted block.</p>
<pre><code>function demo(): void {
    echo "HtmlToWordBundle\n";
}</code></pre>
<p>Paragraph after the block.</p>
HTML,
        'membrete_body' => <<<'HTML'
<h2>Subject: Collaboration proposal</h2>
<p>Dear Sir or Madam,</p>
<p>We are writing to express our organization’s interest in participating in the pilot project described in the
attached documentation. We look forward to your reply.</p>
<p>Yours sincerely,</p>
<p><strong>HtmlToWordBundle demo</strong><br>
Integration · nowo.tech</p>
HTML,
        'informe_body' => <<<'HTML'
<h1>Horizontal panel</h1>
<p>This content fits <strong>landscape</strong> orientation: wide tables with more visible columns.</p>

<table>
  <thead>
    <tr>
      <th>ID</th>
      <th>Service</th>
      <th>Status</th>
      <th>Latency</th>
      <th>Uptime</th>
      <th>Notes</th>
    </tr>
  </thead>
  <tbody>
    <tr><td>1</td><td>API Core</td><td>OK</td><td>42 ms</td><td>99.95%</td><td>No incidents</td></tr>
    <tr><td>2</td><td>PDF</td><td>OK</td><td>120 ms</td><td>99.80%</td><td>Morning peak</td></tr>
    <tr><td>3</td><td>Queues</td><td>Warn</td><td>—</td><td>98.10%</td><td>Check workers</td></tr>
    <tr><td>4</td><td>Storage</td><td>OK</td><td>18 ms</td><td>99.99%</td><td>—</td></tr>
  </tbody>
</table>

<h2>Summary</h2>
<p>The <em>informe</em> profile margins are tuned for horizontal A4 printing.</p>
HTML,
        'paginacion' => <<<'HTML'
<h1>Multi-page document</h1>
<p>This preset mixes long text, explicit page breaks (<code>div.page-break</code>), and tables to force
pagination in Word. Use profile <strong>membrete</strong> or <strong>densidad</strong> to see header, footer, and page numbers.</p>

<h2>Section A</h2>
<p>Maecenas ipsum velit, consectetuer eu lobortis ut, dictum at dui. In rutrum. Sed ac dolor sit amet purus malesuada congue.
Fusce suscipit libero eget elit. Praesent in mauris eu tortor porttitor accumsan. Vivamus porttitor turpis ac leo.</p>
<p>Aliquam erat volutpat. Nunc auctor. Mauris tincidunt sem sed arcu. Vestibulum erat nulla, ullamcorper nec, rutrum non,
nonummy ac, erat. Duis condimentum augue id magna semper rutrum. Aliquam erat volutpat.</p>

<div class="page-break"></div>

<h2>Section B (after page break)</h2>
<p>Pellentesque habitant morbi tristique senectus et netus et malesuada fames ac turpis egestas. Proin mattis lacinia justo.
Etiam dictum tincidunt diam. Suspendisse sagittis ultrices urna. Etiam bibendum elit eget erat.</p>

<table>
  <thead>
    <tr><th>#</th><th>Description</th><th>Value</th></tr>
  </thead>
  <tbody>
    <tr><td>1</td><td>Tall row to use vertical space on the page</td><td>α</td></tr>
    <tr><td>2</td><td>Another data row</td><td>β</td></tr>
    <tr><td>3</td><td>Repeatable table header when splitting across pages</td><td>γ</td></tr>
  </tbody>
</table>

<p>Curabitur vitae diam non enim vestibulum interdum. Donec vitae arcu. Nullam rhoncus aliquam metus. Etiam quis quam.</p>

<div class="page-break"></div>

<h2>Section C</h2>
<p>Final block: Word will paginate according to margins and profile font size. Footer page numbers use the
<code>{PAGE}</code> field generated by PHPWord.</p>
HTML,
        'tipografia_rica' => <<<'HTML'
<h1>Style sheet</h1>
<p style="text-align: center;">Centered paragraph. Mix of <strong>bold</strong>, <em>italic</em>, <u>underline</u>,
<s>strikethrough</s>, and <strong><em>bold + italic</em></strong>.</p>

<p><span style="font-family: Georgia, serif; font-size: 18pt; color: #1e40af;">Georgia 18 pt in blue</span>
 — YAML baseline profile + runs via <code>StyleMapper::fontStyleFromInlineStyle</code>.</p>

<p><span style="font-family: 'Courier New', monospace; font-size: 9pt; color: #444444;">Courier New 9 pt monospace.</span></p>

<p>
  <span style="font-family: Arial, sans-serif; font-size: 14pt;">Arial 14 pt</span>
  ·
  <span style="font-family: 'Times New Roman', serif; font-size: 12pt;"><em>Times New Roman 12 pt italic</em></span>
  ·
  <span style="font-family: Verdana, sans-serif; font-size: 11pt; color: rgb(180, 40, 40);">Verdana 11 pt RGB color</span>
</p>

<p><mark style="background-color: #fef08a;">Highlighter-style mark</mark> and <small>small text</small> next to body copy.</p>

<p>Indices: E = mc<sup>2</sup> · water H<sub>2</sub>O · volume with superscript m<sup>3</sup>.</p>

<p style="text-align: right;"><span style="font-size: 9pt; color: #64748b;">Right-aligned footnote-style line (9 pt grey).</span></p>
HTML,
        'imagen_remota' => <<<'HTML'
<h1>Remote image (HTTP)</h1>
<p>The bundle downloads the URL (PHP HTTP client), writes a temp file, and PHPWord inserts the image (needs network in the container).</p>
<p><img src="https://picsum.photos/id/237/420/280" alt="Remote dog" width="420" height="280" /></p>
<p>Suggested profile: <strong>imagen_ancha</strong> (<code>images.max_width</code> / <code>resolve_remote</code>).</p>
HTML,
    ];

    /**
     * Suggested YAML profile + one line about which stack layer is exercised (PhpWord always produces DOCX).
     *
     * @var array<string, array{profile: string, stack: string}>
     */
    public const PRESET_META = [
        'minimal' => [
            'profile' => 'estricto',
            'stack'   => 'HTML5 (masterminds/html5) → transformers → PhpWord; strict_mode keeps only tags with a transformer.',
        ],
        'articulo' => [
            'profile' => 'default',
            'stack'   => 'Heading/List/Blockquote/Hr + local raster (public/demo/sample.png → absolute path for PhpWord).',
        ],
        'tablas' => [
            'profile' => 'tablas_avanzado',
            'stack'   => 'TableTransformer (PhpWord table/gridSpan); compare with tablas_sin_repeat profile.',
        ],
        'codigo' => [
            'profile' => 'default',
            'stack'   => 'PreTransformer → monospace runs in PhpWord.',
        ],
        'membrete_body' => [
            'profile' => 'membrete',
            'stack'   => 'HeaderFooterBuilder (header image + text, footer + PAGE field in PhpWord).',
        ],
        'informe_body' => [
            'profile' => 'informe',
            'stack'   => 'SectionConfigurator landscape + PhpWord section properties.',
        ],
        'paginacion' => [
            'profile' => 'membrete',
            'stack'   => 'DivTransformer page-break + tblHeader repeatable header + Word pagination.',
        ],
        'imagen_remota' => [
            'profile' => 'imagen_ancha',
            'stack'   => 'ImageResolver (HTTP → temp file) + PhpWord addImage; images.max_width caps width.',
        ],
        'tipografia_rica' => [
            'profile' => 'fuente_times_13',
            'stack'   => 'b/em/u/s/sup/sub + span style (font-family, font-size, color, background-color) via InlineComposer/StyleMapper; switch profile (fuente_arial_16, fuente_courier_11, …) to see YAML base font size.',
        ],
    ];

    /**
     * Default HTML for /custom-config: multiple “pages”, tables, lists, typography, explicit breaks.
     */
    public const CUSTOM_CONFIG_DEFAULT_HTML = <<<'HTML'
<h1>Reference manual — HtmlToWordBundle</h1>
<img src="https://picsum.photos/id/237/420/280" alt="Remote HTTPS image (Picsum)" width="420" height="280" />
<p class="lead"><strong>Demonstration document</strong> mixing blocks to exercise Word export:
headings, lists, tables with headers, quotes, code, inline typography, and page breaks.</p>

<h2>1. Executive summary</h2>
<p>This body mixes several content types in one HTML flow. The goal is a multi-page <em>DOCX</em>
without relying on text volume alone: <code>div.page-break</code> forces breaks where Word will honour flow
according to margins and profile font size.</p>
<ul>
  <li><strong>Unordered lists</strong> with bold items.</li>
  <li>Inline <code>code</code> mixed with running text.</li>
  <li>Links such as <a href="https://symfony.com">visible text</a> (engine may export as plain text).</li>
</ul>

<h3>1.1 Exercise goals</h3>
<ol>
  <li>Verify <code>h1</code>–<code>h3</code> map to consistent paragraph styles.</li>
  <li>Check nested lists and ordered numbering.</li>
  <li>See repeatable table headers when a table spans pages (profile with <code>tables.repeat_header_row</code>).</li>
</ol>

<h2>2. Data table (business)</h2>
<p>First wide table: caption, header, and footer. Amounts are fictional.</p>

<table>
  <caption>Quarterly indicators (demo)</caption>
  <thead>
    <tr>
      <th>Area</th>
      <th>Q1</th>
      <th>Q2</th>
      <th>Q3</th>
      <th>YoY var.</th>
    </tr>
  </thead>
  <tbody>
    <tr>
      <td>Direct sales</td>
      <td style="text-align: right;">128,400 €</td>
      <td style="text-align: right;">134,900 €</td>
      <td style="text-align: right;">141,200 €</td>
      <td style="text-align: right;">+6.8%</td>
    </tr>
    <tr>
      <td>Marketplace</td>
      <td style="text-align: right;">42,100 €</td>
      <td style="text-align: right;">39,800 €</td>
      <td style="text-align: right;">44,500 €</td>
      <td style="text-align: right;">−1.2%</td>
    </tr>
    <tr>
      <td>Support</td>
      <td style="text-align: center;" colspan="3">Internal cost not allocated per line</td>
      <td style="text-align: right;">—</td>
    </tr>
  </tbody>
  <tfoot>
    <tr>
      <td colspan="5" style="text-align: center;"><small>Illustrative data · HtmlToWordBundle demo</small></td>
    </tr>
  </tfoot>
</table>

<div class="page-break"></div>

<h2>3. Quotes, rules, and body text</h2>
<blockquote>
  <p>Quotes should look distinct from the main body. This block uses <code>blockquote</code> with an inner paragraph.</p>
</blockquote>

<hr>

<p>Lorem ipsum dolor sit amet, consectetur adipiscing elit. Integer lacinia, nisl at vestibulum faucibus, urna elit dapibus ligula,
vitae feugiat magna urna sit amet lectus. Sed ut perspiciatis unde omnis iste natus error sit voluptatem accusantium doloremque
laudantium, totam rem aperiam, eaque ipsa quae ab illo inventore veritatis et quasi architecto beatae vitae dicta sunt explicabo.</p>

<p>Nemo enim ipsam voluptatem quia voluptas sit aspernatur aut odit aut fugit, sed quia consequuntur magni dolores eos qui ratione
voluptatem sequi nesciunt. Neque porro quisquam est, qui dolorem ipsum quia dolor sit amet, consectetur, adipisci velit, sed quia
non numquam eius modi tempora incidunt ut labore et dolore magnam aliquam quaerat voluptatem.</p>

<h3>3.1 Second compact table</h3>
<table>
  <thead>
    <tr><th>Phase</th><th>Deliverable</th><th>Status</th></tr>
  </thead>
  <tbody>
    <tr><td>Analysis</td><td>Requirements document</td><td>Done</td></tr>
    <tr><td>Design</td><td>Wireframes + API contract</td><td>In progress</td></tr>
    <tr><td>Implementation</td><td>DOCX module + tests</td><td>Pending</td></tr>
    <tr><td>Deployment</td><td>CI pipeline + artifact</td><td>Pending</td></tr>
  </tbody>
</table>

<div class="page-break"></div>

<h2>4. Code and monospace blocks</h2>
<p>Sample PHP fragment inside <code>pre</code>:</p>
<pre><code>namespace App\Demo;

final class DemoHtmlSamples
{
    public const CUSTOM_CONFIG_DEFAULT_HTML = '...';
}</code></pre>

<p>Paragraph after the block to check vertical spacing between <code>pre</code> and normal text.</p>

<h2>5. Inline typography (runs)</h2>
<p style="text-align: center;">Centered paragraph with <strong>bold</strong>, <em>italic</em>, <u>underline</u>, and <s>strikethrough</s>.</p>

<p>
  <span style="font-family: Georgia, serif; font-size: 14pt;">Georgia 14 pt</span>
  ·
  <span style="font-family: 'Courier New', monospace; font-size: 10pt;">Courier 10 pt</span>
  ·
  <span style="font-family: Arial, sans-serif; color: #1d4ed8;">Arial brand blue</span>
</p>

<p>Formulas and indices: energy <em>E</em> = <em>m</em>c<sup>2</sup> · water H<sub>2</sub>O · volume in m<sup>3</sup>.</p>

<p><mark style="background-color: #fef9c3;">Highlighted text</mark> and <small>simulated footnote size</small>.</p>

<h2>6. Closing filler (pagination)</h2>
<p>Maecenas ipsum velit, consectetuer eu lobortis ut, dictum at dui. In rutrum. Sed ac dolor sit amet purus malesuada congue.
Fusce suscipit libero eget elit. Praesent in mauris eu tortor porttitor accumsan. Vivamus porttitor turpis ac leo. Aliquam erat
volutpat. Nunc auctor. Mauris tincidunt sem sed arcu. Vestibulum erat nulla, ullamcorper nec, rutrum non, nonummy ac, erat.</p>

<p>Pellentesque habitant morbi tristique senectus et netus et malesuada fames ac turpis egestas. Proin mattis lacinia justo.
Etiam dictum tincidunt diam. Suspendisse sagittis ultrices urna. Etiam bibendum elit eget erat. Curabitur vitae diam non enim
vestibulum interdum. Donec vitae arcu. Nullam rhoncus aliquam metus. Etiam quis quam.</p>

<p>Final paragraph: if the profile adds header/footer with page numbers, confirm earlier breaks spread content across
multiple sheets when opening the DOCX in Word or LibreOffice.</p>
HTML;

    public static function html(string $preset): ?string
    {
        return self::PRESETS[$preset] ?? null;
    }

    /**
     * @return array<string, string>
     */
    public static function presetsWithResolvedSampleImage(string $absoluteSampleImagePath): array
    {
        $presets = self::PRESETS;
        if (isset($presets['articulo'])) {
            $presets['articulo'] = str_replace(
                self::DEMO_SAMPLE_IMAGE_PLACEHOLDER,
                $absoluteSampleImagePath,
                $presets['articulo'],
            );
        }

        return $presets;
    }

    /** @return list<string> */
    public static function presetIds(): array
    {
        return array_keys(self::PRESETS);
    }
}
