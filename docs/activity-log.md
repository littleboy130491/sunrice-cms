# Activity log

**Manage → Activity log** shows who created, changed or deleted what in the
admin, and when:

| Date | User | Action | Activity | Details |
| --- | --- | --- | --- | --- |
| 9 Oct 2026, 10:42 | Rina | published | Published entry “About us (Pages)” | title (en), data.body (en) |
| 9 Oct 2026, 10:40 | Rina | updated | Updated entry “About us (Pages)” | title (en, draft) |
| 9 Oct 2026, 09:15 | Admin | updated | Updated setting “Site settings” | name, timezone |
| 8 Oct 2026, 17:02 | System | updated | Updated entry “Launch (News)” | title (id, draft) |

## What's recorded

- **Content**: entries (created, updated, published, unpublished, trashed,
  restored, deleted), terms, menus and their items, globals, assets and
  folders, forms and deleted submissions.
- **Structure**: collections, taxonomies, blueprints and fieldsets.
- **People**: users (and their roles), roles (and their permissions), AI
  access tokens.
- **Settings**: site settings and the homepage. A person's own table
  preferences (columns, rows per page) aren't recorded.
- **Resources**: created, updated, deleted, and their
  [actions](resources.md#actions) ("Mark as paid").
- **Reordering** a collection's entries, a taxonomy's terms, a menu or the
  collections list: one line each time.

Each line keeps the user's name as it was, so it still reads well after the
user is deleted. **Details** lists what changed: field names, with the
language for translated fields (`title (en)`), and `draft` for changes
saved as a draft. Values aren't stored, only which fields changed.

- Several changes to one thing in one save become one line: saving an entry
  and its translation is one "updated", and creating an entry with its
  first translation is one "created".
- Changes from the console or the queue (`sunrice:translate`, an import
  command) show as **System**. AI agents act as the user their token
  belongs to.
- Visitors aren't recorded: a form submission isn't admin activity.

## Filtering

Filter by **period** (last 24 hours, 7, 30 or 90 days), **action**, **type**
(entry, term, setting…) and **user** (or System), and search by the name of
what changed or the user's name.

## Permissions

| Permission | |
| --- | --- |
| `sunrice.activity.view` | See the activity log. |
| `sunrice.activity.prune` | Delete old entries with the Prune button. |

Super admins and the Administrator role have both. Give them to other roles
in **Users → Roles**.

## Keeping it small

The log grows with every save. Delete old entries:

- In the admin: **Prune** at the top of the log, with the age in days
  (180 by default).
- From the command line:

  ```bash
  php artisan sunrice:prune-activity            # older than 180 days
  php artisan sunrice:prune-activity --days=30  # older than 30 days
  ```

- Every day, by scheduling the command in `routes/console.php`:

  ```php
  Schedule::command('sunrice:prune-activity')->daily();
  ```

The default age is `sunrice.activity.prune_days` (180). Each prune adds a
line of its own: "Pruned activity log", with how many entries were deleted.

## Turning it off, and imports

Set `SUNRICE_ACTIVITY_LOG=false` in `.env` to stop recording. To leave a
bulk import out of the log, wrap it:

```php
app(\Sunrice\Activity\ActivityLogger::class)->withoutLogging(function () {
    // create thousands of entries…
});
```

`sunrice:demo-content` does this already.

## Recording your own activity

A package or app can add lines, e.g. for its own models:

```php
app(\Sunrice\Activity\ActivityLogger::class)->record('refunded', $order, ['changes' => ['status']], 'order');
```

The arguments are the action, the model (or a text for things without
one), extra details (`changes`: a list of what changed; `note`: a text) and
the type shown in the log.
