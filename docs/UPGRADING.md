# Upgrading

## Table of contents

- [Unreleased](#unreleased)
- [To 1.4.0](#to-140)
- [To 1.3.0](#to-130)
- [To 1.2.6](#to-126)
- [To 1.2.4](#to-124)
- [General](#general)
- [1.2.2 → 1.2.3](#122--123)
- [1.2.1 → 1.2.2](#121--122)
- [1.2.0 → 1.2.1](#120--121)
- [Configuration root key rename](#configuration-root-key-rename)
- [`HtmlToWordConverterInterface` implementors (v1.0.0+)](#htmltowordconverterinterface-implementors-v100)
- [1.0.x → 1.1.0](#10x--110)
  - [`DocxExporter` constructor](#docxexporter-constructor)
- [1.1.4 → 1.2.0](#114--120)
- [1.1.3 → 1.1.4](#113--114)
- [1.1.2 → 1.1.3](#112--113)
- [1.1.1 → 1.1.2](#111--112)
- [1.1.0 → 1.1.1](#110--111)
- [Version 1.x](#version-1x)

## Unreleased

## To 1.4.0

From **1.3.0** — header/footer and heading_map options implemented, flysystem adapter wired and optional, dead keys deprecated.

```bash
composer update nowo-tech/html-to-word-bundle
```

- **`league/flysystem` is no longer installed by the bundle.** If you call `DocxExporter::toFlysystem()`, run `composer require league/flysystem` (3.x).
- **Header/footer output may change** if your profiles already set keys that used to be ignored: `header.show_page_number: true` now prints a page number in the header, `*.different_first_page: true` now leaves the first page's header/footer empty, and non-default `styles.heading_map` values are now applied (`HeadingN` = title level N; other values = Word paragraph style name, which must exist in the document).
- **`export.flysystem_adapter`** is now used: when set, `toFlysystem()` writes to that service (it must exist and implement `League\Flysystem\FilesystemOperator`, otherwise the container fails to compile or `ExportException` is thrown) instead of the injected `$flysystem`.
- **Deprecated keys** (no effect, now trigger deprecations): `fonts.fallback`, `fonts.size_unit`, `styles.custom_class_map`, `export.storage`, `export.local_path` — remove them from your YAML.

## To 1.3.0

From **1.2.6** — security: host-exact image allowlist, pinned/limited remote downloads, local paths confined, safe Content-Disposition.

```bash
composer update nowo-tech/html-to-word-bundle
```

- **Local `<img src>` paths** (e.g. `/var/www/public/logo.png`) are no longer read unless you set `images.local_base_dir` to the directory that contains them; other schemes (`file://`, `ftp://`…) are always rejected. Header/footer `logo` paths from configuration are unaffected.
- **`remote_host_allowlist`**: substring matches are gone — list the host (`cdn.example.com`, or `example.com` for all subdomains). Regex entries (`#…#`) now match the **host** (e.g. `#^cdn\d\.example\.com$#`), not the full URL.
- **Remote images**: redirects are not followed (use the final URL), hosts must resolve to public IPs only (set `images.remote_allow_private_network: true` for intranet hosts), and downloads above `images.remote_max_bytes` (10 MiB) fail.

## To 1.2.6

From **1.2.4** / **1.2.5** — dependency refresh.

```bash
composer update nowo-tech/html-to-word-bundle
```

- No breaking changes. No application upgrade steps.

## To 1.2.4

From **1.2.3** — REQ-CS-008 Igor FrankenPHP worker audit (igor-php require-dev, igor.json, make igor).

```bash
composer update nowo-tech/html-to-word-bundle
php bin/console cache:clear
```

- No application upgrade steps for require-dev Igor tooling (REQ-CS-008). Consumers do not pull `igor-php/igor-php` transitively.

## General

- Always read the release notes on GitHub for the tag you are upgrading to.
- Review `docs/CHANGELOG.md` for breaking changes and deprecations.
- Run `composer update nowo-tech/html-to-word-bundle` and clear Symfony cache in your application:

```bash
php bin/console cache:clear
```

## 1.2.2 → 1.2.3

No configuration change. Constructor changes are additive (new optional last arguments):

- `ImageResolver(?TemporaryImageFiles $temporaryFiles = null)`, `RemoteHttpImageInliner(HtmlParser, ImageResolverInterface, ?TemporaryImageFiles $temporaryFiles = null)`, `WordDocument(object, ResolvedConfig, string, array $temporaryFiles = [])`.
- `RemoteHttpImageInliner::cleanupInlineSession()` still exists and now deletes every tracked temp image not released yet. `DocxExporter` calls the new `releaseTemporaryFiles($document)` instead, so exporting one document does not delete the images of another document built in the same request.
- If you build `WordDocumentBuilder` / `ImageResolver` by hand (outside the container), pass the same `TemporaryImageFiles` instance to `ImageResolver` and `RemoteHttpImageInliner` so data URI temp files are cleaned too.
- The cron / tmpfs workaround for `htw_*` files from the worker-mode audit is no longer needed.
- FrankenPHP workers without kernel reset are supported for temp-image lifecycle — see [FRANKENPHP-WORKER-AUDIT.md](FRANKENPHP-WORKER-AUDIT.md).

```bash
composer update nowo-tech/html-to-word-bundle
php bin/console cache:clear
```

## 1.2.1 → 1.2.2

No application upgrade steps.

```bash
composer update nowo-tech/html-to-word-bundle
```

## 1.2.0 → 1.2.1

No application upgrade steps. Demos only: Hot Reload Bundle `^1.4` (FrankenPHP Mercure/`hot_reload`, `dev`/`test`).

## Configuration root key rename

If you used **`html_to_word`** as the YAML root key and **`config/packages/html_to_word.yaml`**, rename both to **`nowo_html_to_word`** / **`config/packages/nowo_html_to_word.yaml`**, and update env/parameters that referenced **`%html_to_word.*%`** to **`%nowo_html_to_word.*%`**.

Symfony DI tags for transformers and engines remain **`html_to_word.transformer`** and **`html_to_word.engine`** (unchanged).

## `HtmlToWordConverterInterface` implementors (v1.0.0+)

The bundle’s default converter gained **`convertWithInlineProfile()`**. If you maintain a **custom implementation** of `HtmlToWordConverterInterface`, add this method and route it to your engine with `ResolvedConfig::fromArray($profileConfig)` (or equivalent), matching the profile subtree shape documented under `nowo_html_to_word.profiles` in [CONFIGURATION.md](CONFIGURATION.md).

## 1.0.x → 1.1.0

### `DocxExporter` constructor

If you **instantiate** `Nowo\HtmlToWordBundle\Export\DocxExporter` yourself (unit tests, custom wiring), the constructor is now:

```php
public function __construct(
    \Nowo\HtmlToWordBundle\Parser\RemoteHttpImageInliner $remoteHttpImageInliner,
    ?\League\Flysystem\FilesystemOperator $flysystem = null,
) {}
```

Previously it was only `?FilesystemOperator $flysystem = null`. Inject `RemoteHttpImageInliner` first (or obtain both services from the Symfony container and pass them in).

Applications that only use **`ExporterInterface`** from the container require **no code changes** — the bundle definition already wires the inliner.

## 1.1.4 → 1.2.0

No breaking application code changes are required.

- **Consumers:** `composer update nowo-tech/html-to-word-bundle` and clear cache as usual.
- **Optional:** set `images.remote_timeout` (seconds, default `10`) under a profile’s `images` section if you need a different remote download timeout — see [CONFIGURATION.md](CONFIGURATION.md).
- **Security:** when `images.resolve_remote` is enabled, set an explicit `remote_host_allowlist` (empty allowlist is deny-all via `RemoteImageHostPolicy`) — see [SECURITY.md](SECURITY.md).
- **Maintainers:** coverage gate is now **100%** lines; demo FrankenPHP image uses PHP **8.5** with optional `FRANKENPHP_MODE`.

## 1.1.3 → 1.1.4

No application code or configuration changes are required.

- **Consumers:** `composer update nowo-tech/html-to-word-bundle` and clear cache as usual (test-only fix).

## 1.1.2 → 1.1.3

No application code or configuration changes are required.

- **Consumers:** `composer update nowo-tech/html-to-word-bundle` and clear cache as usual.
- **Maintainers / local demos:** `demo/symfony7` is gone — use `demo/symfony8` (or `make -C demo up`). Aggregate `DEMOS` is now `symfony8` only; `up8` / `release-check8` remain aliases.

## 1.1.1 → 1.1.2

No application code or configuration changes are required.

- **Consumers:** `composer update nowo-tech/html-to-word-bundle` and clear cache as usual.
- **Maintainers / local dev:** if you copied demo Makefile patterns from this bundle, define **`COMPOSE`**, **`COMPOSE_FILE`**, and **`SERVICE_PHP`** before including `Makefile.demo-update-deps.mk` in each `demo/symfony*/Makefile` — see fixed demos in this release.

## 1.1.0 → 1.1.1

No application code or configuration changes are required.

- **Consumers:** `composer update nowo-tech/html-to-word-bundle` and clear cache as usual.
- **Maintainers / local dev:** if you copied demo Makefile patterns from this bundle, ensure the demo aggregator defines **`DEMOS`** (e.g. `symfony7 symfony8`) before including `Makefile.demo-aggregate-update-deps.mk`, and use `$(BUNDLE_ROOT)/../.scripts/...` for shared includes — see fixed `demo/Makefile` in this release.

## Version 1.x

Future breaking changes will be listed here with migration steps.
