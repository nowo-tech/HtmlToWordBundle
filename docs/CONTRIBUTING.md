# Contributing

Thank you for improving HtmlToWordBundle.

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
