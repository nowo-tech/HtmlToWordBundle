# Contributing

Thank you for improving HtmlToWordBundle.


## Code of Conduct

This project follows the [Contributor Covenant Code of Conduct](../CODE_OF_CONDUCT.md). By participating, you are expected to uphold it. Please report unacceptable behavior to **hectorfranco@nowo.tech**.
## Workflow

1. Open an issue (bug report or feature request) using the templates in `.github/ISSUE_TEMPLATE`.
2. Fork the repository and create a branch from `main`.
3. Keep changes focused; match existing coding style (PHP-CS-Fixer configuration in `.php-cs-fixer.dist.php`).
4. Run quality checks locally:

```bash
make up          # or ensure Docker is running
make qa
make test-coverage
```

5. Update `docs/CHANGELOG.md` under **Unreleased** when user-visible behaviour changes.
6. Open a pull request using `.github/PULL_REQUEST_TEMPLATE.md`.

## Tests

- PHPUnit suites live under `tests/Unit` and `tests/Integration`.
- Prefer covering new behaviour with tests; integration tests may boot a minimal Symfony kernel (`tests/Fixtures/AppKernel.php`).

## Security

Please report security issues privately as described in [SECURITY.md](SECURITY.md) and `.github/SECURITY.md`; do not open public issues for undisclosed vulnerabilities.

## Git hooks (REQ-GIT-001)

Do **not** add `Co-authored-by: Cursor` or `cursoragent@cursor.com` trailers to commit messages.

```bash
make setup-hooks
make check-no-cursor-coauthor
```

`make setup-hooks` installs `.githooks/commit-msg` (or sets `core.hooksPath` to `.githooks`). Run it once per clone before your first commit.
If CI fails because trailers are already on the remote, see [GITHUB_CI.md](GITHUB_CI.md) (REQ-GIT-001) and run `make strip-cursor-coauthor-from-history` before `git push --force-with-lease`.
