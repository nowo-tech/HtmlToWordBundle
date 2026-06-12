# ENGRAM — HtmlToWordBundle semantics

Repository-local **product spec** and **`REQ-*`** traceability (Makefiles, demos) are described in [Spec-driven development](SPEC-DRIVEN-DEVELOPMENT.md).

This document summarizes **meaning**, **inputs/outputs**, and **composition** for assistants and maintainers.

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
2. **HtmlParser** — HTML5 parse into `DOMDocument` with a `<body>` wrapper.
3. **WordDocumentBuilder** — one section: section layout + optional header/footer, then depth-first dispatch on `body` children.
4. **TransformerChain** — first transformer matching the element name (priority order) runs; unknown tags respect `strict_mode`.
5. **InlineComposer** — maps inline tags (`strong`, `a`, `img`, …) inside `TextRun` / cells / list runs.

## Extension points

Implement `Nowo\HtmlToWordBundle\Transformer\TransformerInterface` and tag the service `html_to_word.transformer` (bundle uses `_instanceof` autoconfiguration).

## Non-goals

- Editing existing `.docx` files
- PDF export
- Twig variable rendering
