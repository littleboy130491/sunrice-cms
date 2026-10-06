# Permissions

Sunrice uses `spatie/laravel-permission`. Guard: `sunrice.auth.guard`.
The role named by `sunrice.super_admin_role` bypasses every check
(`Gate::before`).

Run `php artisan sunrice:sync-permissions` after adding collections,
taxonomies, forms or resources — it creates missing permissions and
deletes stale ones.

## Global permissions

| Permission | Grants |
| --- | --- |
| `sunrice.access-admin` | Access to the admin panel. |
| `sunrice.manage-structure` | Collections, blueprints, fieldsets, menus, taxonomies, forms, globals CRUD. |
| `sunrice.assets.view` / `upload` / `delete` | Asset library. |
| `sunrice.settings.edit` | Settings screens (homepage, locales). |

## Entity-scoped permissions

| Pattern | Actions |
| --- | --- |
| `sunrice.entries.{collectionId}.{action}` | `view`, `create`, `edit`, `edit-own`, `delete`, `delete-own`, `publish` |
| `sunrice.terms.{taxonomyId}.{action}` | `view`, `create`, `edit`, `delete` |
| `sunrice.forms.{formId}.{action}` | `edit`, `view-submissions`, `export-submissions`, `delete-submissions` |
| `sunrice.resources.{key}.{action}` | `view`, `create`, `edit`, `delete`, `export` |

Model resources additionally honor the model's own policy when one is
registered.
