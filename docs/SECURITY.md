# Security — HtmlToWordBundle

This document describes the **attack surface**, **threats**, and **controls** for `nowo-tech/html-to-word-bundle`. It is written in English per project standards.

## Scope

The bundle converts **HTML strings** into **WordprocessingML (.docx)** files using PHPWord. It may:

- Parse untrusted HTML (sanitizer + DOM parsing).
- Resolve **local paths**, **data URIs**, and optionally **remote URLs** for `<img src>` when enabled.
- Write temporary files during image handling and export.
- Write output files or streams requested by the application.

It does **not** expose HTTP routes by itself; embedding apps choose authorization.

## Attack surface

| Input | Source | Notes |
|-------|--------|--------|
| HTML string | Application code / stored user content | Primary input; drives DOM structure and inline styles. |
| YAML configuration | Symfony config | Margins, fonts, header/footer paths, `images.resolve_remote`, `strict_mode`, export paths. |
| Image URLs / paths | Embedded in HTML | Subject to SSRF / path traversal if misused. |
| Flysystem adapter | Injected service | Writes depend on adapter configuration and ACLs in the host app. |

## Threats and mitigations

### HTML / script injection

- **Risk**: Active content in HTML leading to unsafe downstream handling.
- **Mitigation**: `HtmlSanitizer` removes `<script>`, `<style>`, `<iframe>`/`<object>`/`<embed>` before parsing; DOM walk strips `on*` attributes. The bundle does not execute scripts.

### SSRF via remote images

- **Risk**: When `images.resolve_remote` is true, `file_get_contents()` may fetch attacker-controlled URLs from the server network.
- **Mitigation**: Keep remote resolution **disabled** in sensitive deployments; terminate outbound traffic at the network layer; prefer base64 or controlled CDN URLs at the application layer. HTTP timeouts are limited (10s) in `ImageResolver`.

### Path traversal / local file read via `<img src>`

- **Risk**: Paths like `/etc/passwd` could be read if readable by the PHP user.
- **Mitigation**: Only paths starting with `/` and passing `is_readable()` are used; validate paths in the application before conversion if HTML is untrusted.

### Resource exhaustion

- **Risk**: Large HTML or huge images may consume CPU/memory/disk.
- **Mitigation**: Enforce size limits at the application gateway; configure PHP limits; use `images.max_width` to cap embedded bitmap dimensions.

### Unsafe temporary files

- **Risk**: Predictable temp paths or leaked temp files.
- **Mitigation**: PHP `tempnam()` / system temp; exporters unlink temp files after HTTP responses where applicable.

### Dependency vulnerabilities

- **Mitigation**: Run `composer audit` in CI; track PHPWord / Symfony / Flysystem advisories; pin compatible versions.

## Logging and secrets

The bundle does not log HTML content by default. Applications should avoid logging full documents or remote URLs with credentials.

## Cryptography

Not applicable; no custom cryptography in this bundle.

## Reporting

See the repository `.github/SECURITY.md` for coordinated disclosure contacts.
