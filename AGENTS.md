# Working on Sunrice CMS

`SPEC.md` is the source of truth for behaviour. `EXECUTION_PLAN.md` is the source of truth for structure, naming, and order. If they disagree, follow `SPEC.md` and record the conflict in the pull request.

## Rules

- One task per branch and pull request. Branch `task/<task-id>-<short-name>`, PR titled `[<task-id>] <name>`.
- Only start a task when all its dependencies are merged.
- No Composer or npm packages beyond `EXECUTION_PLAN.md` §2. If one seems needed, stop and explain why in the PR instead of adding it.
- Every task ships Pest tests (Orchestra Testbench). Before opening a PR run: `composer test`, `composer lint`, `composer analyse`, and for frontend tasks `npm run typecheck && npm run build`.
- Never modify migrations from a merged task — add a new migration.
- Prefer the simplest implementation that is good enough. Do not implement anything listed under "Out of scope for v1" in `SPEC.md`.
- Update `docs/` when adding developer-facing API (config keys, Blade helpers, service provider registration methods).
- Do not commit `node_modules/`, `vendor/`, or `.env`. Do commit compiled admin assets in `dist/`.

## Conventions

- PHP namespace `Sunrice\` → `src/`; tests `Sunrice\Tests\` → `tests/`. Every PHP file starts `declare(strict_types=1);`.
- Config `config/sunrice.php` (key `sunrice`); views `sunrice::`; tables prefixed `sunrice_`.
- Permission names `sunrice.<area>.<id>.<action>` or `sunrice.<action>`; admin route names `sunrice.admin.<area>.<action>`; frontend `sunrice.frontend.*`.
- Write operations live in single-purpose action classes `src/Actions/<Area>/` with a public `handle()`. Controllers stay thin.
- Admin JS/TS in `resources/js` (TypeScript, React 19, alias `@/`).

## Commands

```bash
composer test      # Pest
composer lint      # Pint (test mode)
composer format    # Pint (fix)
composer analyse   # Larastan
npm run build      # admin assets -> dist/
vendor/bin/testbench serve   # run the workbench CMS locally
```
