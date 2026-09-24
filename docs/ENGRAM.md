# ENGRAM — HtmlToWordBundle semantics

Repository-local **product spec** and **`REQ-*`** traceability (Makefiles, demos) are described in [Spec-driven development](SPEC-DRIVEN-DEVELOPMENT.md).

This document summarizes **meaning**, **inputs/outputs**, and **composition** for assistants and maintainers.

## Table of contents

- [Purpose](#purpose)
- [Configuration cascade](#configuration-cascade)
- [Pipeline](#pipeline)
- [Extension points](#extension-points)
- [Non-goals](#non-goals)

## Purpose

- **In**: HTML string produced by a WYSIWYG or upstream renderer (already expanded; **no Twig** responsibility here).
- **Out**: an OOXML Word document (`.docx`) via PHPWord, optionally streamed or saved through Symfony HTTP responses or Flysystem.

## Configuration cascade

Resolved order (later wins):

1. YAML profile named `default`
2. Named profile selected by the caller / default_profile
3. Ad-hoc array passed to `convertWithOptions()`

The result is an immutable value object: `Nowo\HtmlToWordBundle\Config\ResolvedConfig`.

## Pipeline

1. **HtmlSanitizer** — strip scripts/styles/iframes; strip `on*` attributes on DOM.
2. **RemoteHttpImageInliner** — optional `http(s)` → temp path; tracked in `TemporaryImageFiles` per document.
3. **HtmlParser** — HTML5 parse into `DOMDocument` with a `<body>` wrapper.
4. **WordDocumentBuilder** — one section: section layout + optional header/footer, then depth-first dispatch on `body` children; attaches `temporaryFiles()` for the exporter.
5. **TransformerChain** — first transformer matching the element name (priority order) runs; unknown tags respect `strict_mode`.
6. **InlineComposer** — maps inline tags (`strong`, `a`, `img`, …) inside `TextRun` / cells / list runs.
7. **DocxExporter** — writes DOCX then `releaseTemporaryFiles()` (worker-safe leftovers on `kernel.terminate` / `reset`).

FrankenPHP worker mode without kernel reset: [FRANKENPHP-WORKER-AUDIT.md](FRANKENPHP-WORKER-AUDIT.md) (`REQ-WORKER-001`).

## Extension points

Implement `Nowo\HtmlToWordBundle\Transformer\TransformerInterface` and tag the service `html_to_word.transformer` (bundle uses `_instanceof` autoconfiguration).

## Non-goals

- Editing existing `.docx` files
- PDF export
- Twig variable rendering
