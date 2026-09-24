# FrankenPHP worker mode audit (kernel not reset between requests)

| Field | Value |
|-------|-------|
| Package | `nowo-tech/html-to-word-bundle` (`symfony-bundle`) |
| Audited revision | `v1.2.3` |
| Audit date | 2026-09-24 |
| Method | Manual review of every file under `src/` (services, DI extension, `Resources/config/services.yaml`, transformers, exporter) plus the static state of `phpoffice/phpword` 1.4.0 used by the bundle |
| Remediation (2026-09-23/24) | W-01 and W-02 fixed: temp images are tracked per document (`Builder\TemporaryImageFiles`), deleted after export, and leftovers are deleted on `kernel.terminate` and `kernel.reset`; regression tests run consecutive requests on the same container without `reset()` |
| **Verdict** | ✅ **Viable under scenario B** — no cross-request state; temp images never outlive the request. Remaining items are accepted (W-03 remote download limits, W-04 PHPWord `Settings` owned by the application) |

## Execution model assumed

FrankenPHP worker mode boots the Symfony kernel once per worker and serves many requests with the same container. This audit assumes the **strict** variant: the kernel is **not** rebooted between requests, so every shared service, static property and PHP global survives from one request to the next. Two scenarios are evaluated:

- **A — kernel not rebooted, `services_resetter` still runs:** services tagged `kernel.reset` (or implementing `ResetInterface`) are reset between requests.
- **B — no reset at all:** nothing is reset; any per-request state kept in a service leaks into the next request.

A bundle that is safe under **B** is safe under **A** and under classic mode / PHP-FPM.

## Summary

| Area | Status | Notes |
|------|--------|-------|
| Mutable state in shared services | ✅ | Only `TemporaryImageFiles` (paths of temp images not yet deleted); emptied after each export, on `kernel.terminate` and on `reset()`. All other services are `readonly` or build their arrays once in the constructor |
| Static properties / `static` locals | ✅ | None in the bundle; `ImageStyleHelper`, `ImageSignatureValidator`, `RemoteImageHostPolicy` only have pure static methods |
| `ResetInterface` / `kernel.reset` coverage | ✅ | `TemporaryImageFiles` implements it; correctness under scenario B relies on export cleanup + `kernel.terminate`, not on reset (W-02) |
| Request / user / locale captured in services | ✅ | HTML and `ResolvedConfig` are passed as method arguments; nothing is read from `RequestStack` or `TokenStorage` |
| Superglobals, `$_ENV`, `putenv`, `ini_set`, `setlocale`, timezone | ✅ | None used; config is compiled into container parameters |
| Doctrine / EntityManager | ✅ N/A | No persistence |
| Output, headers, `exit`, shutdown functions | ✅ | Output only through Symfony `StreamedResponse` (`php://output` inside the callback) and `BinaryFileResponse` |
| Resources (files, sockets, cURL) held open | ✅ | No handle is kept open; temp images are deleted after export or at request end (W-01, resolved) |
| Memory growth across requests | ✅ | No caches or accumulating arrays; PHPWord static registries are reset by each `new PhpWord()` (W-04) |
| Blocking I/O and timeouts | ⚠️ Low | Remote images use `file_get_contents()` with a configurable read timeout (default 10 s) but no total duration or size cap (W-03, accepted) |
| Third-party static state | ⚠️ Info | PHPWord `Media::$elements`, `Style::$styles`, `Settings::*` are process-global; the bundle never writes `Settings` (W-04) |
| PHPStan FrankenPHP rulesets | ✅ | `ruleset-classic.neon` + `ruleset-worker.neon` included in `phpstan.neon.dist` |

## Services reviewed

| Service | Shared | Mutable state | Scenario A | Scenario B |
|---------|--------|---------------|------------|------------|
| `Nowo\HtmlToWordBundle\Converter\HtmlToWordConverter` | yes (public) | none (`final readonly`) | ✅ | ✅ |
| `Nowo\HtmlToWordBundle\Engine\EngineRegistry` | yes | `$enginesByName`, filled once in the constructor | ✅ | ✅ |
| `Nowo\HtmlToWordBundle\Engine\PhpWordEngine` | yes | none (`final readonly`) | ✅ | ✅ |
| `Nowo\HtmlToWordBundle\Builder\WordDocumentBuilder` | yes | none (`final readonly`) | ✅ | ✅ |
| `Nowo\HtmlToWordBundle\Config\ProfileResolver` | yes | none (`final readonly`, profiles from container parameters) | ✅ | ✅ |
| `Nowo\HtmlToWordBundle\Parser\RemoteHttpImageInliner` | yes | none (delegates to `TemporaryImageFiles`) | ✅ | ✅ |
| `Nowo\HtmlToWordBundle\Builder\ImageResolver` | yes | none; registers every temp file it creates in `TemporaryImageFiles` | ✅ | ✅ |
| `Nowo\HtmlToWordBundle\Builder\TemporaryImageFiles` | yes | pending temp paths + per-document collections; `ResetInterface` + `kernel.terminate` subscriber | ✅ | ✅ |
| `Nowo\HtmlToWordBundle\Export\DocxExporter` | yes | none (`final readonly`, optional Flysystem operator) | ✅ | ✅ |
| `Nowo\HtmlToWordBundle\Transformer\TransformerChain` | yes | `$sorted`, built once in the constructor | ✅ | ✅ |
| 9 transformers (`Paragraph`, `Heading`, `List`, `Table`, `Blockquote`, `Pre`, `Hr`, `Div`, `ImageBlock`) | yes | none (stateless or `readonly` dependencies, `private const` only) | ✅ | ✅ |
| `HtmlParser`, `HtmlSanitizer`, `InlineComposer`, `StyleMapper`, `SectionConfigurator`, `HeaderFooterBuilder` | yes | none (stateless; `HtmlParser` creates a new `Masterminds\HTML5` per call) | ✅ | ✅ |

Value objects (`ResolvedConfig`, `WordDocument`) are created per call and are `readonly`; the bundle never stores them in a service.

## Findings

### W-01 — Temp files for `data:` URI images are never deleted (Medium)

- **Where:** `src/Builder/ImageResolver.php:103-121` (`fromDataUri()` writes `htw_b64_*` with `tempnam()` + `file_put_contents()`), called from `src/Transformer/ImageBlockTransformer.php:56` and `src/Builder/InlineComposer.php:140`. Only paths registered by `RemoteHttpImageInliner` (`src/Parser/RemoteHttpImageInliner.php:112`) are removed by `cleanupInlineSession()`; the paths returned to the transformers are not tracked anywhere. The file is only unlinked when signature validation fails (`ImageResolver.php:132-134`).
- **Worker impact:** every conversion that contains a base64 image leaves one file per image in `sys_get_temp_dir()`. A FrankenPHP container runs for a long time and nothing cleans `/tmp` between requests, so disk usage grows without bound and image content from user X stays on disk after the request ends. This is not strictly worker-specific (PHP-FPM has the same leak), but it is more visible in long-lived containers. `ImageResolver.php:43` also accepts any readable local path as `<img src>`, so leftover temp images could in theory be embedded by another user who knows the random `tempnam()` name; guessing it is hard, so this is noted as an aggravating factor, not a High finding.
- **Recommendation:** register every temp path created by `ImageResolver` (data URIs and HTTP downloads) in the same per-conversion list that `DocxExporter` cleans in its `finally` blocks, or pass `data:` bytes to PHPWord without a temp file. Integrators on the current version should run a periodic cleanup of `htw_b64_*` / `htw_img_*` files in the temp dir, or point `sys_get_temp_dir()` to a volume that is purged.
- **Status:** Resolved — `ImageResolver` registers every temp file it creates in the new shared `src/Builder/TemporaryImageFiles.php`. `WordDocumentBuilder::build()` collects the files of one conversion and stores them in `WordDocument::temporaryFiles()`; a failed conversion deletes its files immediately. `DocxExporter` deletes the document's files in its `finally` blocks (`toStreamResponse()` inside the streaming callback). Files that were never exported are deleted by `TemporaryImageFiles` on `kernel.terminate` (priority -1024, after the response was streamed) and on `reset()`. Tests: `tests/Integration/WorkerModeTemporaryFilesTest.php`, `tests/Unit/Builder/TemporaryImageFilesTest.php`.

### W-02 — `RemoteHttpImageInliner` keeps a temp-file list across requests without `ResetInterface` (Low)

- **Where:** `src/Parser/RemoteHttpImageInliner.php:34` (`$sessionTempFiles`), filled at `:112`, cleared by `cleanupInlineSession()` (`:45-54`), which is called at the start of `inlineRemoteImages()` (`:61`) and in the `finally` blocks of `DocxExporter` (`src/Export/DocxExporter.php:48`, `:76`, `:97`).
- **Worker impact:** if a request converts a document with remote images but never exports it (exception, early return, or a `StreamedResponse` whose callback never runs), the downloaded `htw_img_*` files stay on disk and in the service property until the next conversion in the same worker thread deletes them. The list only ever holds one conversion, so memory is bounded and no data is served to another user. Because the service has no `reset()`, the same happens under scenario A. A side effect of the shared list (also true in PHP-FPM): converting two documents in the same request and exporting the first one after the second conversion deletes the first document's images before it is written.
- **Recommendation:** implement `ResetInterface` with `reset(): void { $this->cleanupInlineSession(); }` so scenario A always cleans leftovers at request end; long term, move the temp-file list into the `WordDocument` returned by the builder instead of a shared service.
- **Status:** Resolved — `$sessionTempFiles` was removed from `src/Parser/RemoteHttpImageInliner.php`; the inliner registers downloaded files in `TemporaryImageFiles` (per-document collection, `ResetInterface`, `kernel.terminate`). `inlineRemoteImages()` no longer wipes the previous conversion, so two documents built in the same request keep their own images until each one is exported. `cleanupInlineSession()` is kept for BC and deletes every pending temp image.

### W-03 — Remote image download has a read timeout but no total duration or size cap (Low)

- **Where:** `src/Builder/ImageResolver.php:74-82` (`stream_context_create(['http' => ['timeout' => …]])` + `file_get_contents()`), default `images.remote_timeout: 10.0` in `src/DependencyInjection/Configuration.php:135-139`. Remote resolution is disabled by default (`resolve_remote: false`, `:126-129`) and requires an allowlist.
- **Worker impact:** the stream `timeout` is a per-read idle timeout, so a slow server that keeps sending bytes can hold the worker thread longer than the configured value, and HTTP redirects are followed by the stream wrapper by default. The whole body is loaded into memory with no size limit. It pins one of the limited worker threads; it does not leak state.
- **Recommendation:** keep `remote_timeout` low, keep `resolve_remote` off unless needed, and cap waiting requests with FrankenPHP `max_wait_time`. A future version could use Symfony HttpClient with `timeout`, `max_duration`, `max_redirects: 0` and a size limit.
- **Status:** Accepted — off by default, allowlist-only, explicit read timeout; no state leak. Moving to HttpClient is a feature change left for a future version.

### W-04 — PHPWord process-global static state (Info)

- **Where:** `phpoffice/phpword` keeps static registries in `PhpOffice\PhpWord\Media::$elements`, `PhpOffice\PhpWord\Style::$styles` and `PhpOffice\PhpWord\Settings::*`. `new PhpWord()` (called in `src/Builder/WordDocumentBuilder.php:60`) runs `Media::resetElements()`, `Style::resetStyles()` and `Settings::setDefaultRtl(null)`. The bundle never calls `Settings::set*()`.
- **Worker impact:** the media/style registries of the last generated document stay in memory until the next conversion in that worker; they are replaced, not accumulated, so memory is bounded. Any `Settings::set*()` call made by the application (for example `setOutputEscapingEnabled()` or `setTempDir()`) stays in effect for the lifetime of the worker, for every later request.
- **Recommendation:** if the application changes PHPWord `Settings`, do it once at boot with values valid for every request, never per request.
- **Status:** Accepted — the bundle never writes PHPWord `Settings`; `new PhpWord()` resets the media/style registries per conversion. Application-level `Settings` calls remain the application's responsibility.

No other findings. Profiles are compiled into container parameters, and `ResolvedConfig` is rebuilt for every call, so ad-hoc options from one request never reach the next one.

## Usage recommendations in worker mode

- Temp images are deleted after export; `toStreamResponse()` deletes them when the streaming callback runs, otherwise on `kernel.terminate`.
- In long-running CLI workers (Messenger), keep `services_resetter` enabled (default) so documents that are converted but never exported do not leave temp images behind.
- Do not set PHPWord `Settings` per request; do it once at boot.
- Custom transformers (`html_to_word.transformer`) and custom `ImageResolverInterface` implementations are shared services: keep them stateless, or implement `ResetInterface`.
- The demo (`demo/symfony8/docker/frankenphp/Caddyfile`) runs FrankenPHP with a `worker` block, which can be used to reproduce these scenarios.

## Re-audit triggers

Re-run this audit when a change adds: properties to any builder, transformer, parser or exporter service; a cache of resolved images or profiles; new temp-file creation; calls to PHPWord `Settings`; a PHPWord major upgrade; or any use of `RequestStack`, `$_SERVER` or `$_ENV` at runtime.
