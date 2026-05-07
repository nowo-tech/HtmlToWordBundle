# HtmlToWordBundle — Symfony 7 demo

Minimal Symfony 7 application using **FrankenPHP** in Docker.

## Quick start

```bash
cp .env.example .env
make up
```

Follow **`Demo started at: http://localhost:<PORT>`** (default port **8020**).

## Bundle mount

`docker-compose.yml` mounts the parent bundle at **`/var/html-to-word-bundle`**. Composer resolves `nowo-tech/html-to-word-bundle` via a path repository pointing to that directory.

Use **`make update-bundle`** after changing bundle code.

## Documentation

See [docs/DEMO-FRANKENPHP.md](../../docs/DEMO-FRANKENPHP.md) in the bundle repository.
