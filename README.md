# Sunrice CMS

An easy to set up and extend CMS for Laravel, built as a package — collections, blueprints, fieldsets, taxonomies, navigation, globals, assets and forms, with draft/publish workflow, translations and SEO, in the spirit of Statamic and WordPress ACF.

## Requirements

- PHP 8.3 or newer
- Laravel 13
- sqlite, MySQL or PostgreSQL

## Installation

_(Full instructions land in `docs/installation.md`; the package is under active development.)_

```bash
composer require sunrice/cms
php artisan sunrice:install
```

The admin lives at `/cms` by default.

## Documentation

See [`docs/`](docs/) and [`SPEC.md`](SPEC.md). Contributor/agent rules live in [`AGENTS.md`](AGENTS.md).

## License

MIT.
