# Upgrading

## General

- Always read the release notes on GitHub for the tag you are upgrading to.
- Review `docs/CHANGELOG.md` for breaking changes and deprecations.
- Run `composer update nowo-tech/html-to-word-bundle` and clear Symfony cache in your application:

```bash
php bin/console cache:clear
```

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
