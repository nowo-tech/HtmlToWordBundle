# Feature Specification: HtmlToWordBundle baseline (100% code coverage)

**Feature Branch**: `001-baseline`  
**Status**: Active  

**Package**: `nowo-tech/html-to-word-bundle`  
**Configuration root**: `nowo_html_to_word`  
**Code inventory**: [`code-inventory.md`](code-inventory.md)

---

## Summary

Converts **rich HTML** (WYSIWYG output) into downloadable **DOCX** via **PHPWord**, with **named YAML profiles**, deep merge with per-call overrides, HTML5 parsing and sanitization, **tagged block transformers**, optional remote/inline images with host policy, header/footer support, and Symfony-friendly export (stream, path, Flysystem).

---

## User Scenarios

### US-01 — Profile-based conversion (P1)

**Given** profiles defined under `nowo_html_to_word.profiles` and a `default_profile`, **When** integrator calls `HtmlToWordConverter::convert($html, $profileName)`, **Then** `ProfileResolver` deep-merges profile + call options and returns a `WordDocument`.

### US-02 — Sanitized HTML parse (P1)

**Given** untrusted or WYSIWYG HTML, **When** conversion starts, **Then** `HtmlSanitizer` strips unsafe content and `HtmlParser` builds a DOM for the transformer chain.

### US-03 — Block element mapping (P1)

**Given** standard HTML blocks (`p`, headings, lists, tables, blockquote, pre, hr, div, img), **When** `TransformerChain` walks the document, **Then** each tagged transformer delegates to `WordDocumentBuilder` / `PhpWordEngine`.

### US-04 — Remote images (P2)

**Given** `<img src="https://…">` and allowed hosts in config, **When** `RemoteHttpImageInliner` runs, **Then** images download to temp paths subject to `RemoteImageHostPolicy` and `ImageResolver` before PhpWord embed.

### US-05 — DOCX export (P1)

**Given** a `WordDocument`, **When** integrator uses `DocxExporter`, **Then** binary/stream/path/Flysystem responses are produced without Twig or PDF generation.

---

## Requirements

### Bundle & config

- **FR-BUNDLE-001**: `HtmlToWordBundle` alias `nowo_html_to_word`.
- **FR-CFG-001**: `Configuration` — engine, default profile, profiles (page, fonts, margins, header/footer, image policy).
- **FR-CFG-002**: `HtmlToWordExtension` loads services and profile parameters.
- **FR-CFG-003**: `ProfileResolver`, `ResolvedConfig` — profile merge and inline profile support.

### DI

- **FR-DI-001**: `services.yaml`, `html_to_word.yaml`, shipped `nowo_html_to_word.yaml` example profiles.

### Conversion

- **FR-CONV-001**: `HtmlToWordConverter` orchestrates parse → transform → build.
- **FR-PARSE-001**: `HtmlParser`, `HtmlSanitizer`.
- **FR-PARSE-002**: `RemoteHttpImageInliner`, `RemoteImageHostPolicy`.

### Engine & build

- **FR-ENG-001**: `EngineRegistry`, `PhpWordEngine`, `WordEngineInterface`.
- **FR-MODEL-001**: `WordDocument` in-memory model.
- **FR-BUILD-001**: `WordDocumentBuilder`, section/style/inline composition.
- **FR-BUILD-002**: `HeaderFooterBuilder` for logo, text, page numbers.
- **FR-BUILD-003**: Image resolver, signature validator, style helper.

### Transformers

- **FR-XFORM-001**: Chain, interfaces, walker.
- **FR-XFORM-002**: Element transformers for paragraphs, headings, lists, tables, blockquote, div, pre, hr, images.

### Export & errors

- **FR-EXPORT-001**: `DocxExporter`, `ExporterInterface`.
- **FR-EXC-001**: Typed exceptions for parse, export, engine, profile, image, and unsupported elements.

---

## Success Criteria

- **SC-001**: **49/49** files mapped in inventory.
- **SC-002**: Config keys match [`docs/CONFIGURATION.md`](../../docs/CONFIGURATION.md).
- **SC-003**: `composer qa` / coverage-check (≥85%) green in CI.

---

## Explicit non-goals

- Twig rendering, PDF output, or editing existing Word files.
- Client-side HTML generation.

---

## Validation

`composer qa`, `composer coverage-check`, PHPStan, inventory row audit.
