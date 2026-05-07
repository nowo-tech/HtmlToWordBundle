# Changelog

All notable changes to this project are documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/), and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [Unreleased]

## [1.0.0] - 2026-05-07

### Added

- Symfony bundle for **HTML → DOCX** conversion using **PHPWord**, with **named YAML profiles**, **HTML5** parsing (**masterminds/html5**), sanitization, **tagged transformers**, and **Symfony exporters** (HTTP stream/binary, local file, optional **Flysystem**).
- `HtmlToWordConverterInterface::convertWithInlineProfile(string $html, array $profileConfig): WordDocument` — converts using a **full profile-shaped configuration array** with **no YAML merge** (e.g. JSON loaded from a database). Same structure as one profile under `nowo_html_to_word.profiles`.
- Compatibility with Symfony **6.4**, **7.x**, and **8.x** (see `composer.json`).

### Changed

- **BC:** Symfony configuration root key is **`nowo_html_to_word`** (was `html_to_word`). Rename `config/packages/html_to_word.yaml` → `nowo_html_to_word.yaml` and parameters `%html_to_word.*%` → `%nowo_html_to_word.*%`. DI tags `html_to_word.transformer` / `html_to_word.engine` are unchanged.

[Unreleased]: https://github.com/nowo-tech/HtmlToWordBundle/compare/v1.0.0...HEAD
[1.0.0]: https://github.com/nowo-tech/HtmlToWordBundle/releases/tag/v1.0.0
