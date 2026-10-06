# Assets

The asset library stores files on `sunrice.assets.disk` under
`sunrice.assets.directory` (`{Y}/{m}/{random}-{slug}.{ext}`).

- `sunrice.assets.max_upload_kb` caps upload size; `image_sizes` defines
  named derivatives `{name: [width, height, mode]}` with `crop` (cover)
  or `fit` (scale-down) mode; the stored `sizes` map holds
  `{name: path}`.
- SVGs are never resized; `Asset::url('thumbnail')` returns the
  derivative, `Asset::url()` the original; `Asset::thumbnail()` picks the
  first available derived size.
- Replacing an asset keeps the path, bumps `version`, deletes stale
  derivative files and regenerates them.
- `sunrice:regenerate-images {--id=}` rebuilds missing derivatives.
- Folders are a `parent_id` tree (no path strings to maintain); moving
  assets never renames files.
- Usage tracking: `Asset::usages()` lists entries/globals referencing
  the asset (kept by `SyncReferences` on every save); deleting an asset
  in use requires confirmation (`force`).

## Optimizing images

`sunrice:optimize-images` shrinks oversized images and recompresses them in
place. The file keeps its path, so URLs and content references don't
change; the asset's version is bumped (cache-busting `?v=`) and its image
sizes are regenerated.

```bash
php artisan sunrice:optimize-images                       # use the config defaults
php artisan sunrice:optimize-images --max-width=1920 --quality=75
php artisan sunrice:optimize-images --id=12 --id=15       # only these assets
php artisan sunrice:optimize-images --dry-run             # report only, write nothing
php artisan sunrice:optimize-images --no-backup           # don't keep the originals
php artisan sunrice:optimize-images --restore             # put the originals back
```

Defaults live in `sunrice.assets.optimize`:

| Key | Default | Meaning |
| --- | --- | --- |
| `max_width` / `max_height` | `2560` | Images larger than this are scaled down to fit (aspect ratio kept). |
| `quality` | `82` | JPEG, WebP and AVIF quality (1–100). PNGs are re-encoded losslessly. |
| `backup` | `true` | Copy each original before overwriting it. |
| `backup_disk` | `null` | Disk for the copies; `null` uses the asset's own disk. |
| `backup_directory` | `sunrice-originals` | Folder the copies go into, mirroring the asset path. |

Notes:

- GIFs (to keep animations) and SVGs are skipped. An image that already fits
  is only rewritten when recompressing makes it smaller.
- The backup always holds the file as first uploaded: later runs never
  overwrite it. `--restore` puts it back and deletes the backup.
- Replacing or permanently deleting an asset also removes its backup.
- Backups on the `public` disk are publicly reachable, like the originals
  were. Set `backup_disk` to a private disk (e.g. `local`) to keep them
  private.
