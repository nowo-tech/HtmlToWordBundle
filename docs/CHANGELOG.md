# Changelog

All notable changes to this project are documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/), and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [Unreleased]

## [1.1.0] - 2026-05-07

### Added

- **`RemoteHttpImageInliner`** — before PhpWord runs, resolves remote `<img src="http(s)://…">` to a **temporary absolute path** (PhpWord-friendly); optional cache per URL in one conversion. Requires `images.resolve_remote` (default `true`).
- **`DocxExporter`** calls `RemoteHttpImageInliner::cleanupInlineSession()` **after** the Word writer `save()` completes so raster bytes are embedded in the DOCX (not before).
- **`ImageStyleHelper::completeEmbeddingStyle()`** — when `getimagesize()` fails, width/height still converted from HTML attributes (CSS px → pt) with sensible defaults.
- **Demo** (`demo/symfony7`, `demo/symfony8`): `DemoHtmlSamples::headerFooterDemoOverlay()`, `/custom-config` pre-filled JSON for `header` / `footer`, block-level sample images, `public/demo/sample.png`.

### Changed

- **`DocxExporter` constructor** — first argument `RemoteHttpImageInliner`, second `?FilesystemOperator $flysystem` (unchanged order after that). **Apps using autowiring:** no change. **Manual `new DocxExporter(...)`** (e.g. tests): pass an inliner instance first — see [UPGRADING.md](UPGRADING.md).
- **`WordDocumentBuilder`** service definition uses explicit `autowire: false` and ordered constructor arguments so `RemoteHttpImageInliner` is injected correctly.

### Fixed

- Word displaying “The picture can’t be displayed…” for remote images when temp files were deleted before PhpWord read them during `save()`.

## [1.0.0] - 2026-05-07

### Added

- Symfony bundle for **HTML → DOCX** conversion using **PHPWord**, with **named YAML profiles**, **HTML5** parsing (**masterminds/html5**), sanitization, **tagged transformers**, and **Symfony exporters** (HTTP stream/binary, local file, optional **Flysystem**).
- `HtmlToWordConverterInterface::convertWithInlineProfile(string $html, array $profileConfig): WordDocument` — converts using a **full profile-shaped configuration array** with **no YAML merge** (e.g. JSON loaded from a database). Same structure as one profile under `nowo_html_to_word.profiles`.
- Compatibility with Symfony **6.4**, **7.x**, and **8.x** (see `composer.json`).

### Changed

- **BC:** Symfony configuration root key is **`nowo_html_to_word`** (was `html_to_word`). Rename `config/packages/html_to_word.yaml` → `nowo_html_to_word.yaml` and parameters `%html_to_word.*%` → `%nowo_html_to_word.*%`. DI tags `html_to_word.transformer` / `html_to_word.engine` are unchanged.

[Unreleased]: https://github.com/nowo-tech/HtmlToWordBundle/compare/v1.1.0...HEAD
[1.1.0]: https://github.com/nowo-tech/HtmlToWordBundle/releases/tag/v1.1.0
[1.0.0]: https://github.com/nowo-tech/HtmlToWordBundle/releases/tag/v1.0.0
