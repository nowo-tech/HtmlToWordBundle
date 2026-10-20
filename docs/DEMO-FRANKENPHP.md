# Demo applications with FrankenPHP (HtmlToWordBundle)

**REQ-DEMO-001:** FrankenPHP demos must install **Nowo Twig Inspector** and **Nowo Hot Reload** together (`nowo-tech/twig-inspector-bundle` + `nowo-tech/hot-reload-bundle` in `require-dev`). Caddyfile: Mercure + `hot_reload` (and `worker { file …; watch }` in worker mode). Do not enable Hot Reload in production.

This mirrors the standard Nowo pattern documented for other bundles: demos live only in the **Git repository** (not in the Composer artifact) and use **FrankenPHP** (Caddy + PHP) with Docker Compose.

## Table of contents

- [Overview](#overview)
- [What this demo includes](#what-this-demo-includes)
- [Volumes and Composer path repo](#volumes-and-composer-path-repo)
- [DNS (Composer / Packagist)](#dns-composer--packagist)
- [Development vs production Caddyfiles](#development-vs-production-caddyfiles)
- [Switching classic vs worker (`FRANKENPHP_MODE`)](#switching-classic-vs-worker-frankenphp_mode)
- [Timeouts (REQ-RUNTIME-001)](#timeouts-req-runtime-001)
- [Start the demo (development)](#start-the-demo-development)
- [Start the demo (production)](#start-the-demo-production)
- [Troubleshooting](#troubleshooting)

## Overview

- **Symfony 8 demo**: `demo/symfony8` — Compose project **`html-to-word-bundle-demo-symfony-8`** (REQ-DOC-002). FrankenPHP layout with PHP **8.5**, Symfony **8**. Default port **8021**.
- **Bundle mount**: host `../..` → container `/var/html-to-word-bundle`.
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

The demo (`demo/symfony8/composer.json`) contains:

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

The demo ships two Caddyfiles: `docker/frankenphp/Caddyfile` (worker mode: `php_server { hot_reload; worker { file /app/public/index.php; watch } }`) and `docker/frankenphp/Caddyfile.dev` (classic `php_server { hot_reload }`, no worker). Both keep `root * /app/public`, `encode zstd br gzip` and Mercure (`order mercure after encode`, `mercure { anonymous }`) for Nowo Hot Reload. The entrypoint (`docker/entrypoint.sh`) selects the file from **`FRANKENPHP_MODE`** (see below), not from `APP_ENV`.

**Worker mode without kernel reset:** HtmlToWordBundle is audited for FrankenPHP workers that keep the same container across requests (scenario B). Temp images are cleaned after export or on `kernel.terminate` / `kernel.reset` — see [FRANKENPHP-WORKER-AUDIT.md](FRANKENPHP-WORKER-AUDIT.md) (REQ-WORKER-001).

## Switching classic vs worker (`FRANKENPHP_MODE`)

Demos select the FrankenPHP runtime via **`FRANKENPHP_MODE`** in `.env` / `.env.example` (not a Dockerfile `ENV`):

| Value | Behaviour |
| --- | --- |
| **`worker`** (default) | Keep the worker Caddyfile (`php_server { worker ... }`) |
| **`classic`** | Entrypoint copies `Caddyfile.dev` (plain `php_server`, hot-reload friendly) |

Compose passes `FRANKENPHP_MODE=${FRANKENPHP_MODE:-worker}` into the PHP service. After changing `.env`, run `docker compose up -d` (or `make up`) so the container is **recreated** — a plain `restart` does not reload env. No image rebuild is required.

## Timeouts (REQ-RUNTIME-001)

The only blocking work in the bundle is the optional download of remote `<img src>` URLs (`images.resolve_remote`). Keep the deadlines layered so the innermost one fires first:

| Layer | Setting | Demo value |
| --- | --- | --- |
| Operation | `nowo_html_to_word.profiles.<name>.images.remote_timeout` (per download) | **10 s** (bundle default) |
| PHP | `max_execution_time` — must be greater than the sum of the remote downloads of one export | PHP default (30 s) |
| HTTP server | Caddy `servers { timeouts { write … } }` — must be greater than PHP | Caddy default (no write timeout) |
| FrankenPHP | `max_wait_time` in the `frankenphp` global block — caps the wait for a free PHP thread | not set in the demo |

When you raise `remote_timeout` in production, raise `max_execution_time` and the Caddy write timeout in the same change, and set `max_wait_time` (e.g. 30 s) so a busy worker pool answers 504 instead of queueing forever.

## Start the demo (development)

From the bundle root:

```bash
make -C demo/symfony8 up        # copies .env.example to .env when missing, builds and starts the stack
make -C demo/symfony8 install   # composer install + cache clear (when the Makefile provides it)
```

Then open `http://localhost:$PORT` (`PORT` comes from `demo/symfony8/.env`). For a hot-reload friendly runtime set `FRANKENPHP_MODE=classic` in `.env` (the entrypoint copies `docker/frankenphp/Caddyfile.dev`, plain `php_server` without `worker`) and recreate the container with `docker compose up -d`. Keep `APP_ENV=dev`, `APP_DEBUG=1`, the mounted `docker/php-dev.ini` (`opcache.revalidate_freq=0`) and Twig cache disabled in dev.

## Start the demo (production)

The worker Caddyfile (`docker/frankenphp/Caddyfile`, `php_server { worker { file /app/public/index.php; watch } }`) is the default runtime (`FRANKENPHP_MODE=worker`). To run the demo with a production profile:

1. In `demo/symfony8/.env` set `APP_ENV=prod`, `APP_DEBUG=0`, a real `APP_SECRET` and keep `FRANKENPHP_MODE=worker`.
2. Do not mount `docker/php-dev.ini` (dev-only OPcache revalidation) and do not enable Nowo Hot Reload / Twig Inspector (dev-only bundles).
3. Install without dev dependencies and warm the cache inside the container: `composer install --no-dev --optimize-autoloader` and `php bin/console cache:clear --env=prod`.
4. Recreate the container: `docker compose up -d` (a plain `restart` does not reload environment variables).

Worker mode keeps the kernel booted between requests; see the [worker audit](FRANKENPHP-WORKER-AUDIT.md) when it exists for the services reviewed for request-to-request state.

## Troubleshooting

- **Code or template changes not visible:** worker mode keeps the kernel in memory. Switch to `FRANKENPHP_MODE=classic` (or rely on `watch` in the worker Caddyfile), make sure `docker/php-dev.ini` is mounted and Twig cache is off in dev, then hard-refresh the browser.
- **`FRANKENPHP_MODE` change ignored:** run `docker compose up -d` (or `make up`) so the container is recreated; `docker compose restart` keeps the old environment.
- **Port already in use / demo does not answer:** change `PORT` in `demo/symfony8/.env`, then check `docker compose logs php` for Caddy or PHP errors.
- **Web Profiler toolbar missing:** check `APP_ENV=dev`, `APP_DEBUG=1` and that WebProfilerBundle is enabled for `dev` in `config/bundles.php`; clear the cache with `docker compose exec php php bin/console cache:clear`.
- **Bundle changes not picked up:** the bundle is mounted from the repository root; run `make -C demo/symfony8 update-bundle` (or `composer update nowo-tech/*`) and clear the cache.

