# Usage

## Injecting services

- `Nowo\HtmlToWordBundle\Converter\HtmlToWordConverterInterface` — converts HTML strings to `WordDocument`.
- `Nowo\HtmlToWordBundle\Export\ExporterInterface` — builds Symfony responses or writes files / Flysystem.

## Converting HTML

```php
use Nowo\HtmlToWordBundle\Converter\HtmlToWordConverterInterface;

// Default profile from YAML
$document = $converter->convert($html);

// Named profile
$document = $converter->convertWithProfile($html, 'carta_comercial');

// Options layered on top (third argument null = use configured default_profile key)
$document = $converter->convertWithOptions($html, [
    'page' => ['orientation' => 'landscape'],
    'export' => ['filename' => 'report.docx'],
], null);

// Full profile array only — no YAML merge (e.g. JSON decoded from a database)
$document = $converter->convertWithInlineProfile($html, [
    'strict_mode' => false,
    'export' => ['filename' => 'stored-profile.docx'],
    // …same keys as under nowo_html_to_word.profiles.<name> in YAML
]);
```

## Exporting

```php
use Nowo\HtmlToWordBundle\Export\ExporterInterface;

$response = $exporter->toStreamResponse($document);
$response = $exporter->toBinaryResponse($document);
$exporter->toFile($document, '/tmp/out.docx');
$exporter->toFlysystem($document, 'exports/out.docx'); // requires Flysystem injection
```

If you **bypass** `DocxExporter` and call `IOFactory::createWriter($document->phpWord(), 'Word2007')->save($path)` yourself, run **`RemoteHttpImageInliner::cleanupInlineSession()`** after a successful `save()` when the HTML contained resolved remote images (or rely on the next conversion’s cleanup). Prefer the exporter for a correct lifecycle.

## HTML expectations

- Pass **final HTML** as produced by your editor or renderer (no Twig placeholders left unresolved upstream).
- Scripts and dangerous markup are stripped before conversion (see sanitizer).
- For remote images, enable `images.resolve_remote` and ensure outbound HTTP is acceptable for your environment.

## Images: URLs in stored HTML (recommended)

Keep `<img src="https://…">` (or `http://`) in the HTML you persist. **Immediately before** DOM parsing and PhpWord conversion, **`RemoteHttpImageInliner`** downloads each remote `src` to a **temporary file** and sets `src` to that **absolute path** (same approach as using `Html::addHtml` with local files—more reliable in PhpWord than `data:` URIs). Temp files are removed **after** `DocxExporter` finishes writing the DOCX (PhpWord reads image paths during `save()`). Your database/CMS copy stays URL-based.

- Requires `images.resolve_remote: true` (default). If `false`, URLs are left unchanged and conversion falls back to `ImageResolver` at render time (same rules as before).
- Duplicate URLs in one document are fetched once (small in-memory cache per conversion).
- Protocol-relative URLs (`//cdn.example.com/x.png`) are normalized to `https:` before download.

You can still use **data URIs** or **local file paths** in `src` directly (the bundle’s `ImageResolver` normalizes data URIs to temp files for PhpWord). **Header/footer `header.logo`** remains a **local filesystem path** only.

## Images: uploads without URL

After a Symfony `UploadedFile`, save the file where PHP can read it and put that **absolute path** in `src` when building the HTML string server-side (browsers cannot set OS paths). Alternatively store the file server-side and use an `https://` URL your app serves — `RemoteHttpImageInliner` will resolve it to a temp path before conversion as above.

## Extending behaviour

Register additional `Nowo\HtmlToWordBundle\Transformer\TransformerInterface` implementations and tag them `html_to_word.transformer`. Higher `getPriority()` runs first when multiple transformers match the same tag.
