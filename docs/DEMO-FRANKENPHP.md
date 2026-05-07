# Demo applications with FrankenPHP (HtmlToWordBundle)

This mirrors the standard Nowo pattern documented for other bundles: demos live only in the **Git repository** (not in the Composer artifact) and use **FrankenPHP** (Caddy + PHP) with Docker Compose.

## Contents

- [Overview](#overview)
- [What this demo includes](#what-this-demo-includes)
- [Volumes and Composer path repo](#volumes-and-composer-path-repo)
- [DNS (Composer / Packagist)](#dns-composer--packagist)
- [Development vs production Caddyfiles](#development-vs-production-caddyfiles)

---

## Overview

- **Symfony 7 demo**: `demo/symfony7` — Compose project **`html-to-word-bundle-demo-symfony-7`** (REQ-DOC-002). Default port **8020**.
- **Symfony 8 demo**: `demo/symfony8` — Compose project **`html-to-word-bundle-demo-symfony-8`**. Same FrankenPHP layout and bundle mount; PHP **8.4**, Symfony **8**. Default port **8021** so it can run alongside the Symfony 7 stack.
- **Bundle mount** (both demos): host `../..` → container `/var/html-to-word-bundle`.
- **Port**: configure `PORT` in `.env` / `.env.example`; `make up` prints **`Demo started at: http://localhost:<PORT>`** (REQ-DEMO-005).

---

## What this demo includes

Aligned with **REQ-DEMO-001** for Symfony demos:

- **Web Profiler** + **Debug bundle** (dev/test).
- **Nowo Twig Inspector** (`nowo-tech/twig-inspector-bundle`, dev/test).
- **HtmlToWordBundle** registered in `config/bundles.php`.

There is a single page: choose a YAML profile, optionally load a curated HTML preset (tables, lists, blockquotes, `<pre>`, page-break `<div class="page-break">`, long text for pagination), then submit → streamed `.docx` download. Header/footer demos use `public/demo-assets/demo-logo.png`.

---

## Volumes and Composer path repo

Each demo (`demo/symfony7/composer.json`, `demo/symfony8/composer.json`) contains:

```json
"repositories": [
  { "type": "path", "url": "/var/html-to-word-bundle" }
],
"require": {
  "nowo-tech/html-to-word-bundle": "@dev"
}
```

Editing bundle sources on the host updates what Composer installs inside the container on **`make update-bundle`**.

---

## DNS (Composer / Packagist)

`docker-compose.yml` sets **`dns: [8.8.8.8, 8.8.4.4]`** so Composer can resolve `repo.packagist.org` reliably on some Docker/WSL setups (**REQ-DEMO-009**).

---

## Development vs production Caddyfiles

The FrankenPHP image ships two Caddyfiles (`docker/frankenphp/Caddyfile` and `Caddyfile.dev`). The Dockerfile entrypoint copies **`Caddyfile.dev`** when `APP_ENV=dev` so you get fast iteration without worker mode; production uses workers and stricter caching (same pattern as other Nowo bundle demos).

For full narrative tables (worker mode, cache headers, troubleshooting), see the canonical write-up in sibling bundles (e.g. Icon Selector’s `docs/DEMO-FRANKENPHP.md`) — behaviour is identical; only paths and bundle names differ.
