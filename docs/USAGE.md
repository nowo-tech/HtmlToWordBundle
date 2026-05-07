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
```

## Exporting

```php
use Nowo\HtmlToWordBundle\Export\ExporterInterface;

$response = $exporter->toStreamResponse($document);
$response = $exporter->toBinaryResponse($document);
$exporter->toFile($document, '/tmp/out.docx');
$exporter->toFlysystem($document, 'exports/out.docx'); // requires Flysystem injection
```

## HTML expectations

- Pass **final HTML** as produced by your editor or renderer (no Twig placeholders left unresolved upstream).
- Scripts and dangerous markup are stripped before conversion (see sanitizer).
- For remote images, enable `images.resolve_remote` and ensure outbound HTTP is acceptable for your environment.

## Extending behaviour

Register additional `Nowo\HtmlToWordBundle\Transformer\TransformerInterface` implementations and tag them `html_to_word.transformer`. Higher `getPriority()` runs first when multiple transformers match the same tag.
