# Sunrice CMS

An easy to set up and extend CMS for Laravel, built as a package — collections, blueprints, fieldsets, taxonomies, navigation, globals, assets and forms, with draft/publish workflow, translations and SEO, in the spirit of Statamic and WordPress ACF.

## Requirements

- PHP 8.3 or newer
- Laravel 13
- sqlite, MySQL or PostgreSQL

## Quick start

```bash
composer require sunrice/cms
php artisan sunrice:install
```

The installer publishes the config and the prebuilt admin assets, runs
the migrations, creates the `Super Admin` role and offers to create a
super admin user. Open the admin at `/cms` (set `SUNRICE_ADMIN_PATH` to
change it).

Your user model needs `Spatie\Permission\Traits\HasRoles` — the
installer warns if it is missing. Add a scheduler entry for scheduled
publishing and submission pruning: `* * * * * php artisan schedule:run`.

After upgrades: `php artisan sunrice:publish-assets && php artisan migrate && php artisan sunrice:sync-permissions`.

## Documentation

| Topic | Doc |
| --- | --- |
| Install & configure | [docs/installation.md](docs/installation.md), [docs/configuration.md](docs/configuration.md) |
| Modeling content | [docs/content-modeling.md](docs/content-modeling.md), [docs/custom-fields.md](docs/custom-fields.md) |
| Frontend | [docs/templates.md](docs/templates.md), [docs/blade-components.md](docs/blade-components.md), [docs/helpers.md](docs/helpers.md) |
| Locales | [docs/multilingual.md](docs/multilingual.md), [docs/translation.md](docs/translation.md) |
| Admin extras | [docs/resources.md](docs/resources.md), [docs/permissions.md](docs/permissions.md), [docs/forms.md](docs/forms.md), [docs/assets.md](docs/assets.md) |
| Ops | [docs/caching.md](docs/caching.md), [docs/upgrading.md](docs/upgrading.md) |

The full product contract lives in [`SPEC.md`](SPEC.md); the build plan
in [`EXECUTION_PLAN.md`](EXECUTION_PLAN.md); contributor/agent rules in
[`AGENTS.md`](AGENTS.md).

## License

MIT.
