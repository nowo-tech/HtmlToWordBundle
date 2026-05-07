# Configuration

## Table of contents

- [Root key](#root-key)
- [`engine`](#engine)
- [`default_profile`](#default_profile)
- [`profiles`](#profiles)
- [Merge order](#merge-order)
- [Flysystem wiring](#flysystem-wiring)

## Root key

All options live under `nowo_html_to_word` in your application YAML (e.g. `config/packages/nowo_html_to_word.yaml`).

## `engine`

Conversion backend identifier. Currently supported:

| Value       | Runtime dependency | Notes |
|-------------|-------------------|--------|
| `phpword` (default) | `phpoffice/phpword` (required by this bundle) | Full HTML pipeline via transformers. |

Changing only this key switches the implementation used by `HtmlToWordConverter` (`convert`, `convertWithProfile`, `convertWithOptions`). At runtime, if the selected engine’s classes are missing, `EngineNotAvailableException` is thrown with the Composer package(s) to install.

To add another engine: implement `Nowo\HtmlToWordBundle\Engine\WordEngineInterface`, register the service (the bundle tags `WordEngineInterface` with `html_to_word.engine`), add the engine name to `Nowo\HtmlToWordBundle\DependencyInjection\Configuration::SUPPORTED_ENGINES`, and wire any exporter if the output format differs from PHPWord `.docx`.

## `default_profile`

Name of the profile used when you call `convert()` or `convertWithOptions()` without specifying another profile. That profile **must** exist under `profiles`.

## `profiles`

Each profile can define:

- `strict_mode` — if `true`, unknown HTML elements trigger `UnsupportedElementException` when no transformer is registered.
- `page` — `size` (A4, Letter, Legal, CUSTOM), `orientation` (portrait, landscape), `custom_width` / `custom_height` (for CUSTOM, in twips), `margins` (top, right, bottom, left in twips).
- `fonts` — `default`, `fallback`, `size_unit`, `default_size`.
- `styles` — `heading_map` (h1–h6 to Word style names), `paragraph_spacing` (before/after in twips), `custom_class_map` (CSS class → Word style name).
- `header` / `footer` — enablement, `logo` path, `logo_width`, `text`, `show_page_number`, `different_first_page`.
- `images` — `max_width` (px), `resolve_remote` (download remote `<img src>` URLs).
- `export` — `filename`, `storage`, `local_path`, `flysystem_adapter`.
- `tables` — `repeat_header_row`.

## Merge order

Configuration is merged as:

1. Profile named `default` (always as base).
2. The named profile you select (`convertWithProfile`, or `convertWithOptions(..., $profile)`).
3. Ad-hoc options passed to `convertWithOptions()` (deepest wins).

See `Nowo\HtmlToWordBundle\Config\ProfileResolver`.

## Flysystem wiring

Override the exporter service:

```yaml
services:
    Nowo\HtmlToWordBundle\Export\DocxExporter:
        arguments:
            $flysystem: '@your.filesystem.operator.service.id'
```

If `$flysystem` is `null`, `toFlysystem()` throws `ExportException`.
