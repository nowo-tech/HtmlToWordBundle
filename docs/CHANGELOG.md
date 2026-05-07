# Changelog

All notable changes to this project are documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/), and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [Unreleased]

### Changed

- **BC:** Symfony configuration root key is **`nowo_html_to_word`** (was `html_to_word`). Rename `config/packages/html_to_word.yaml` → `nowo_html_to_word.yaml` and parameters `%html_to_word.*%` → `%nowo_html_to_word.*%`. DI tags `html_to_word.transformer` / `html_to_word.engine` are unchanged.

### Added

- Initial open-source release: HTML → DOCX conversion with YAML profiles, PHPWord, sanitization, tagged transformers, Symfony exporters (stream, binary, file, Flysystem).
