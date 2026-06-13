# HtmlToWordBundle

[![CI](https://github.com/nowo-tech/HtmlToWordBundle/actions/workflows/ci.yml/badge.svg)](https://github.com/nowo-tech/HtmlToWordBundle/actions/workflows/ci.yml)
[![Packagist Version](https://img.shields.io/packagist/v/nowo-tech/html-to-word-bundle.svg?style=flat)](https://packagist.org/packages/nowo-tech/html-to-word-bundle)
[![Packagist Downloads](https://img.shields.io/packagist/dt/nowo-tech/html-to-word-bundle.svg)](https://packagist.org/packages/nowo-tech/html-to-word-bundle)
[![License](https://img.shields.io/badge/license-MIT-blue.svg)](LICENSE)
[![PHP](https://img.shields.io/badge/PHP-8.2%2B-777BB4?logo=php)](https://php.net)
[![Symfony](https://img.shields.io/badge/Symfony-6.4%20%7C%207.4%2B%20%7C%208.0%20%7C%208.1%2B-000000?logo=symfony)](https://symfony.com)
[![GitHub stars](https://img.shields.io/github/stars/nowo-tech/html-to-word-bundle.svg?style=social&label=Star)](https://github.com/nowo-tech/HtmlToWordBundle)
[![Coverage](https://img.shields.io/badge/Coverage-~89%25-green)](#tests-and-coverage)

> ⭐ **Found this useful?** Install from Packagist (`composer require nowo-tech/html-to-word-bundle`) and consider starring [HtmlToWordBundle on GitHub](https://github.com/nowo-tech/HtmlToWordBundle).

Symfony bundle that converts **rich HTML** (WYSIWYG output from TipTap, CKEditor, etc.—already rendered server-side) into a downloadable **`.docx`** using **PHPWord**, with:

- **named YAML profiles** + **default profile** + **deep merge** with per-call options, or **`convertWithInlineProfile()`** for a full stored profile (no YAML merge);
- **sanitization** and HTML5 parsing (**masterminds/html5**);
- **tagged transformers** for block elements (`p`, headings, lists, tables, images, …);
- **remote & inline images** — optional download of `http(s)://` `<img src>` to temp paths before PhpWord, with cleanup after DOCX `save`;
- optional **header/footer** (logo, text, page numbers) per profile;
- **Symfony-friendly export**: streamed/binary responses, local path, optional **Flysystem**.

This bundle does **not** render Twig, generate HTML, produce PDF, or edit existing Word files.

## Documentation

- [Installation](docs/INSTALLATION.md)
- [Configuration](docs/CONFIGURATION.md)
- [Usage](docs/USAGE.md)
- [Contributing](docs/CONTRIBUTING.md)
- [Changelog](docs/CHANGELOG.md)
- [Upgrading](docs/UPGRADING.md)
- [Release](docs/RELEASE.md)
- [Security](docs/SECURITY.md)
- [Engram](docs/ENGRAM.md)
- [Spec-driven development](docs/SPEC-DRIVEN-DEVELOPMENT.md)

### Additional documentation

- [FrankenPHP demos](docs/DEMO-FRANKENPHP.md) — how `demo/symfony7` runs in Docker.
- Example YAML profiles are shipped as `src/Resources/config/nowo_html_to_word.yaml` (copy into your app’s `config/packages/`).

## Requirements

- PHP **8.2+**
- Symfony **6.4 / 7.x / 8.x** (components declared in `composer.json`)
- Extensions: `dom`, `json`, `libxml`

## Quick start

```bash
composer require nowo-tech/html-to-word-bundle
```

Register `Nowo\HtmlToWordBundle\HtmlToWordBundle` if Flex does not, then add `config/packages/nowo_html_to_word.yaml` (see [Configuration](docs/CONFIGURATION.md)).

```php
use Nowo\HtmlToWordBundle\Converter\HtmlToWordConverterInterface;
use Nowo\HtmlToWordBundle\Export\ExporterInterface;

$doc = $converter->convert($html);
return $exporter->toStreamResponse($doc);
```

## Tests and coverage

| Scope | Detail |
|-------|--------|
| **PHPUnit** | `composer test` — unit tests under `tests/Unit`, integration tests under `tests/Integration` (minimal Symfony kernel in `tests/Fixtures/AppKernel.php`). |
| **PHP lines** | Run `composer test-coverage` for the console summary, or `composer coverage-check` (same report written to `coverage-output.txt`, **fails below 85%** global lines). Latest reported global line coverage: **~89%** (PCOV; a few defensive branches in exporters / image temp paths are marked `@codeCoverageIgnore` where impractical to hit in CI). |

CI runs tests, PHPStan, and PHP-CS-Fixer on push/PR (see `.github/workflows/ci.yml`).

## Development

Use the root `Makefile` and `docker-compose.yml`:

```bash
make up
make qa
make release-check
```

The PHP Docker image installs extensions required by PHPWord (including **GD**). Demo apps live under `demo/` (see [docs/DEMO-FRANKENPHP.md](docs/DEMO-FRANKENPHP.md)).

## Version information

Stable releases are tagged on GitHub; upgrade notes are in [docs/UPGRADING.md](docs/UPGRADING.md) and [docs/CHANGELOG.md](docs/CHANGELOG.md).

## Versioning

This library follows [Semantic Versioning](https://semver.org/).

## License

Released under the [MIT License](LICENSE).
