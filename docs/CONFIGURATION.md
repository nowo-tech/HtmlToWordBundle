# Configuration

## Table of contents

- [Root key](#root-key)
- [`engine`](#engine)
- [`default_profile`](#default_profile)
- [`profiles`](#profiles)
- [Merge order](#merge-order)
- [Flysystem wiring](#flysystem-wiring)
- [Deprecated keys](#deprecated-keys)

## Root key

All options live under `nowo_html_to_word` in your application YAML (e.g. `config/packages/nowo_html_to_word.yaml`).

## `engine`

Conversion backend identifier. Currently supported:

| Value       | Runtime dependency | Notes |
|-------------|-------------------|--------|
| `phpword` (default) | `phpoffice/phpword` (required by this bundle) | Full HTML pipeline via transformers. |

Changing only this key switches the implementation used by `HtmlToWordConverter` (`convert`, `convertWithProfile`, `convertWithOptions`, `convertWithInlineProfile`). At runtime, if the selected engine’s classes are missing, `EngineNotAvailableException` is thrown with the Composer package(s) to install.

To add another engine: implement `Nowo\HtmlToWordBundle\Engine\WordEngineInterface`, register the service (the bundle tags `WordEngineInterface` with `html_to_word.engine`), add the engine name to `Nowo\HtmlToWordBundle\DependencyInjection\Configuration::SUPPORTED_ENGINES`, and wire any exporter if the output format differs from PHPWord `.docx`.

## `default_profile`

Name of the profile used when you call `convert()` or `convertWithOptions()` without specifying another profile. That profile **must** exist under `profiles`.

## `profiles`

Each profile can define:

- `strict_mode` — if `true`, unknown HTML elements trigger `UnsupportedElementException` when no transformer is registered.
- `page` — `size` (A4, Letter, Legal, CUSTOM), `orientation` (portrait, landscape), `custom_width` / `custom_height` (for CUSTOM, in twips), `margins` (top, right, bottom, left in twips).
- `fonts` — `default` (font name), `default_size` (points). *Deprecated, no effect:* `fallback`, `size_unit` (see [Deprecated keys](#deprecated-keys)).
- `styles` — `heading_map` (h1–h6 → Word style: `HeadingN` (N = 1–9) renders a PHPWord title of level N, so `h1: Heading2` re-levels the heading and keeps it in the TOC; any other value is applied as a **paragraph style name**, which must exist in the document/template), `paragraph_spacing` (before/after in twips). *Deprecated, no effect:* `custom_class_map`.
- `header` / `footer` — `enabled`, `logo` path (header only), `logo_width` (**CSS px**, converted to typographic points for PHPWord, header only), `text`, `show_page_number` (adds a centered `{PAGE}` field; default `false` for the header, `true` for the footer), `different_first_page` (the first page gets an **empty** header/footer; Word applies this per section, so when only one side sets it the other side's content is repeated on the first page). Both only apply when that side is `enabled`.
- `images` — `max_width` (**CSS px** for HTML `width`/intrinsic size), `resolve_remote` (download remote `<img src>` URLs), `remote_host_allowlist` (matched against the URL **host** only: `example.com` = host + subdomains, `.example.com` = subdomains only, `#regex#` applied to the host), `remote_max_bytes` (default **10485760**; download aborted above it), `remote_allow_private_network` (default **false**; when false every A/AAAA record of the host must be public, the connection is pinned to that IP and redirects are never followed), `local_base_dir` (default **null**; local `<img src>` paths are read only inside this directory — other schemes such as `file://`/`ftp://`/`php://` are always rejected), and `remote_timeout` (seconds, default **10**; HTTP(S) stream timeout for remote downloads — keep below PHP `max_execution_time` / FrankenPHP write timeout). With `resolve_remote` enabled, **before** PhpWord runs the bundle resolves each `http(s)://` `src` to a **temporary absolute path** in memory only (stored HTML can keep URLs); temps are deleted after the DOCX is written (`DocxExporter` / `IOFactory` `save`, when you use the bundle exporter). Values are converted to **points** (`pt = px × 72/96`) before embedding—PhpWord’s image style uses points, not pixels.
- `export` — `filename` (download name), `flysystem_adapter` (service id of a `League\Flysystem\FilesystemOperator` used by `DocxExporter::toFlysystem()` for documents of this profile; see [Flysystem wiring](#flysystem-wiring)). *Deprecated, no effect:* `storage`, `local_path`.
- `tables` — `repeat_header_row`.

## Merge order

Configuration is merged as:

1. Profile named `default` (always as base).
2. The named profile you select (`convertWithProfile`, or `convertWithOptions(..., $profile)`).
3. Ad-hoc options passed to `convertWithOptions()` (deepest wins).

See `Nowo\HtmlToWordBundle\Config\ProfileResolver`.

`convertWithInlineProfile()` **does not** use this merge: it builds `ResolvedConfig` only from the associative array you pass (same keys as a single `profiles.<name>` entry). Use it when the full profile is stored outside YAML (e.g. database JSON).

## Flysystem wiring

`league/flysystem` (3.x) is **optional** (`composer require league/flysystem`). Two ways to give `DocxExporter::toFlysystem()` a filesystem:

1. **Per profile (recommended):** set `export.flysystem_adapter` to the id of a `FilesystemOperator` service. Every id used in `profiles` is exposed to the exporter through a service locator (a missing service fails at container compile; ids not declared in YAML are never reachable). Since profiles merge over `default`, setting it on `default` applies to all profiles.

```yaml
nowo_html_to_word:
    profiles:
        default:
            export:
                flysystem_adapter: 'default.storage'   # e.g. a league/flysystem-bundle storage
```

2. **Exporter-wide fallback:** override the exporter service (the first argument defaults to the bundle’s `RemoteHttpImageInliner`); used when the document's profile has no `flysystem_adapter`:

```yaml
services:
    Nowo\HtmlToWordBundle\Export\DocxExporter:
        arguments:
            $flysystem: '@your.filesystem.operator.service.id'
```

If you replace the whole service definition, wire **`$remoteHttpImageInliner`** explicitly when required by your Symfony version. `toFlysystem()` throws `ExportException` when neither source provides a `FilesystemOperator` (or when `flysystem_adapter` names a service that is not a `FilesystemOperator`, e.g. because `league/flysystem` is not installed).

## Deprecated keys

Deprecated since **1.4** (they never had any effect; setting them now triggers a Symfony deprecation, removal planned for the next major):

| Key | Replacement |
|-----|-------------|
| `fonts.fallback` | None — Word has no per-run font fallback chain; set `fonts.default`. |
| `fonts.size_unit` | None — `fonts.default_size` is always in points. |
| `styles.custom_class_map` | Register a custom transformer (`TransformerInterface`) for class-specific styling. |
| `export.storage` | Pick the target by calling the exporter: `toStreamResponse()` / `toBinaryResponse()` / `toFile()` / `toFlysystem()`. |
| `export.local_path` | Pass the path to `DocxExporter::toFile()`. |
