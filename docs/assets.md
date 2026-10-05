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
