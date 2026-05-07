# Upgrading

## General

- Always read the release notes on GitHub for the tag you are upgrading to.
- Review `docs/CHANGELOG.md` for breaking changes and deprecations.
- Run `composer update nowo-tech/html-to-word-bundle` and clear Symfony cache in your application:

```bash
php bin/console cache:clear
```

## Configuration root key rename

If you used **`html_to_word`** as the YAML root key and **`config/packages/html_to_word.yaml`**, rename both to **`nowo_html_to_word`** / **`config/packages/nowo_html_to_word.yaml`**, and update env/parameters that referenced **`%html_to_word.*%`** to **`%nowo_html_to_word.*%`**.

Symfony DI tags for transformers and engines remain **`html_to_word.transformer`** and **`html_to_word.engine`** (unchanged).

## Version 1.x

No prior public major version; future breaking changes will be listed here with migration steps.
