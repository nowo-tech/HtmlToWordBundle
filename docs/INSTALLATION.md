# Installation

## Table of contents

- [Requirements](#requirements)
- [Install with Composer](#install-with-composer)
- [Symfony Flex recipe](#symfony-flex-recipe)
- [Register the bundle](#register-the-bundle)
- [Configuration file](#configuration-file)
- [Optional: Flysystem](#optional-flysystem)

## Requirements

- PHP 8.2 or newer with extensions: `dom`, `json`, `libxml`
- Symfony 6.4, 7.x, or 8.x (for the components used by this bundle)
- [PHPWord](https://github.com/PHPOffice/PHPWord) (pulled in by Composer)
- [League Flysystem](https://github.com/thephpleague/flysystem) 3.x (optional, for `DocxExporter::toFlysystem()` when you inject an adapter)

## Install with Composer

```bash
composer require nowo-tech/html-to-word-bundle
```

## Symfony Flex recipe

This repository ships a contributor-oriented Flex recipe under `.symfony/recipe/nowo-tech/html-to-word-bundle/` (`manifest.json` + starter `config/packages/nowo_html_to_word.yaml`). When the recipe is merged into `symfony/recipes-contrib`, Flex will register the bundle and copy the default YAML automatically. Until then, follow **Register the bundle** and **Configuration file** below.

## Register the bundle

If your project does not use Symfony Flex, add the bundle class to `config/bundles.php`:

```php
<?php

return [
    // ...
    Nowo\HtmlToWordBundle\HtmlToWordBundle::class => ['all' => true],
];
```

## Configuration file

Create `config/packages/nowo_html_to_word.yaml`. You can start from the example in the package:

`vendor/nowo-tech/html-to-word-bundle/src/Resources/config/nowo_html_to_word.yaml`

Set `default_profile` and at least one profile under `profiles` (the `default` profile is required for merge resolution).

## Optional: Flysystem

To export to a remote or custom filesystem, define a `League\Flysystem\FilesystemOperator` service in your app and pass it as the **second** constructor argument to `Nowo\HtmlToWordBundle\Export\DocxExporter` (after `RemoteHttpImageInliner`; see [CONFIGURATION.md](CONFIGURATION.md) and [UPGRADING.md](UPGRADING.md)).
