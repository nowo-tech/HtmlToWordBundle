# HtmlToWordBundle — demos

Demo applications are **not** shipped in the Composer package (`archive.exclude` includes `/demo`). Clone this repository to run them.

## Symfony 8 (`demo/symfony8`)

FrankenPHP + Docker Compose (PHP **8.4**, Symfony **8**). Default port **8021**.

```bash
cd demo/symfony8
cp .env.example .env   # adjust PORT if needed (default 8021)
make up
```

Open the printed URL. The UI includes **several HTML presets** (tables, headings, code blocks, page breaks, multi-page text) and multiple **`nowo_html_to_word` profiles** (landscape, header/footer + logo + page numbers, export filenames). Pick a profile, load a preset into the textarea (or paste your own HTML), then download the `.docx`.

The **`/custom-config`** route opens a form where you edit **profile JSON** merged with YAML or used inline (`convertWithInlineProfile`). It ships with a **pre-filled example** for Word **header** (`header.logo`, `header.text`) and **footer** (`footer.text`, page numbers); adjust and download.

The bundle source is mounted at **`/var/html-to-word-bundle`** (see `docker-compose.yml`).

**Logo asset:** `public/demo-assets/demo-logo.png` is a raster export (PNG) of the marketing SVG from  
`https://nowo.tech/wp-content/uploads/2022/10/nowo.tech-logo.svg` — Word/PhpWord need PNG here; the original SVG is copied beside it as `nowo.tech-logo.svg` for reference or future regeneration.

See [docs/DEMO-FRANKENPHP.md](../docs/DEMO-FRANKENPHP.md) for architecture (development vs production Caddyfiles, DNS for Composer, etc.).

## Aggregate commands (from `demo/`)

```bash
make up               # delegates to symfony8/Makefile (REQ-DEMO-005)
make up8              # alias of make up
make release-verify   # HTTP 200/302 via symfony8 `verify-http`
make release-verify8  # alias of make release-verify
make release-check    # REQ-DEMO-007: update-bundle, then test + verify-http (symfony8)
make release-check8   # alias of make release-check
```
