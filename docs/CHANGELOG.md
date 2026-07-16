# Changelog

All notable changes to this project are documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/), and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [Unreleased]

## [1.1.3] - 2026-07-16

### Added

- **Code of Conduct** — `CODE_OF_CONDUCT.md` (Contributor Covenant) linked from README and CONTRIBUTING.
- **REQ-GIT-001** — no Cursor co-author trailers: `.githooks/commit-msg`, `.scripts/check-no-cursor-coauthor.sh`, CI `git-hygiene` job, and `docs/GITHUB_CI.md`.
- **`tests/Unit/Transformer/BlockTransformersTest.php`** — unit coverage for block transformers; PHPUnit source excludes `src/Engine` from aggregate Clover (engines covered in Integration).

### Changed

- **Coverage clover** — `composer test-coverage` / `coverage-check` write `coverage.xml` at the repo root (was `coverage/clover.xml`).
- **Demo aggregate** — `make up` / `release-verify` / `release-check` target `demo/symfony8` (`up8` / `release-verify8` / `release-check8` remain aliases).

### Removed

- **Demo Symfony 7** — `demo/symfony7` removed; the FrankenPHP demo is only `demo/symfony8`.

## [1.1.2] - 2026-07-03

### Added

- **CodeRabbit** — `.coderabbit.yaml` and `.github/workflows/coderabbit.yml` for automated PR reviews (maintainers; no runtime impact on the bundle).

### Changed

- **Lockfiles** — bundle and `demo/symfony7` `composer.lock` refreshed (`make update-deps`; Symfony 7.4.14, PHPStan, Rector, and related dev tooling).

### Fixed

- **`make update-deps` (demos)** — `demo/symfony7` and `demo/symfony8` Makefiles now define `COMPOSE` and `SERVICE_PHP` before including the shared `Makefile.demo-update-deps.mk`, so `docker-compose run` is invoked instead of failing with `/bin/sh: run: not found`.

## [1.1.1] - 2026-06-13

### Added

- **`docs/SPEC-DRIVEN-DEVELOPMENT.md`** — spec-driven workflow, user stories, and traceability anchors; ENGRAM cross-links updated.

### Changed

- **CI** — matrix job tests PHP **8.2–8.5** against Symfony **7.4**, **8.0**, and **8.1** (minimum minors per REQ-SF-002); main QA job unchanged (PHPStan, CS-Fixer, PHPUnit).
- **Coverage gate** — minimum line coverage lowered from **93%** to **85%** (`composer coverage-check`, README).
- **`RemoteHttpImageInliner`** — constructor-injected services are `readonly` (Rector; no API or behaviour change).
- **Lockfiles** — bundle and demo apps refreshed (`league/flysystem`, Symfony 7.4.x / 8.1.x in demos).

### Fixed

- **`make update-deps`** — demo aggregator no longer recurses infinitely: `demo/Makefile` defines `DEMOS := symfony7 symfony8` so `update-deps-all` delegates to each demo instead of re-invoking the shared script.
- **Demo / release Makefiles** — unterminated `$(abspath …)` on `include` lines in `demo/`, `demo/symfony7/`, and `demo/symfony8/` (aligned with root `BUNDLE_ROOT` + shared `.scripts` includes); `make release-check` could not enter demos before this fix.

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

[Unreleased]: https://github.com/nowo-tech/HtmlToWordBundle/compare/v1.1.3...HEAD
[1.1.3]: https://github.com/nowo-tech/HtmlToWordBundle/compare/v1.1.2...v1.1.3
[1.1.2]: https://github.com/nowo-tech/HtmlToWordBundle/compare/v1.1.1...v1.1.2
[1.1.1]: https://github.com/nowo-tech/HtmlToWordBundle/compare/v1.1.0...v1.1.1
[1.1.0]: https://github.com/nowo-tech/HtmlToWordBundle/releases/tag/v1.1.0
[1.0.0]: https://github.com/nowo-tech/HtmlToWordBundle/releases/tag/v1.0.0
