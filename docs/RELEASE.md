# Release process

Maintainers cut releases from the default branch using **annotated Git tags** `vMAJOR.MINOR.PATCH`.

## Steps

1. Ensure `main` (or the release branch) passes CI and local `make release-check`.
2. Update `docs/CHANGELOG.md`: move items from **Unreleased** to a new `## [x.y.z]` section with the release date (YYYY-MM-DD), and add compare links at the bottom if used.
3. Commit changelog (and any UPGRADING / USAGE updates).
4. Create an annotated tag with release notes (same message as GitHub Release body when possible):

```bash
git tag -a v1.1.0 -m "Release v1.1.0 — remote image inlining + exporter cleanup; see docs/CHANGELOG.md"
git push origin v1.1.0
```

5. GitHub Actions **release.yml** creates or updates the GitHub Release from the tag (and can pull changelog content from `docs/CHANGELOG.md`).
6. **sync-releases.yml** can backfill missing releases for existing tags (scheduled or manual).

Packagist tracks tags automatically for `nowo-tech/html-to-word-bundle`.

## Security checklist (12.4.1)

Before tagging a release, verify at least:

- `docs/SECURITY.md` is up to date and **no secrets** are committed (`.env*` ignored as appropriate).
- Demo / recipe configuration contains **no credentials**; inputs are validated or sanitized by the app layer where relevant.
- Run **`composer audit`** on the release branch and review PHPWord / Symfony advisories.
- Logging does not leak sensitive payloads (HTML snapshots, tokens, etc.).
- If cryptographic or signing features are added later, follow project crypto and permissions guidelines.

See also `.github/SECURITY.md` for the repository reporting policy.
