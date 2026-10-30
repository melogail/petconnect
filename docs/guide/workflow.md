# Daily workflow

## Before you change anything

1. Open `.ai/rules/index.md`. It maps path globs to rule files. Read every rule file whose glob covers the files you are about to touch.
2. `grep -rin '<keyword>' .ai/rules` for anything a path match alone would miss.
3. Check the sibling files of whatever you are creating and copy their shape.

The rule files are not style notes. They record measured behaviour, security decisions and traps that cost hours the first time (middleware ordering, soft-delete cascades, medialibrary temp directories, Nova exception handling).

## Making changes

- **New files**: `php artisan make:...` with `--no-interaction`. Pipelines have their own generator, `php artisan make:pipeline`.
- **Routes**: every mutating route carries a named throttle. There is no uncapped write route in the app; adding one is a regression. See [Request pipeline](/architecture/middleware#rate-limiting).
- **Authorization**: `$this->authorize()` in the controller, never in a Form Request.
- **GET never writes**: Inertia v3 prefetches links, so a GET with a side effect fires on hover.
- **Frontend routes**: import from `@/actions/...` or `@/routes/...` (Wayfinder). Never hard-code a URL.
- **Translations**: every new UI string goes into both `lang/en.json` and `lang/ar.json`. A parity test fails the suite if a key is missing on one side.

## Formatting and static analysis

```bash
vendor/bin/pint path/to/changed.php   # Laravel Pint, explicit paths
composer types:check                  # PHPStan via Larastan (level set in phpstan.neon)
npm run check                         # vite-plus lint/format check for resources/js
npm run types:check                   # vue-tsc
```

Run Pint with explicit paths rather than `--dirty` when more than one person or agent is working in the tree. PHPStan currently carries a known backlog of pre-existing errors; a red PHPStan run is not by itself a blocker, new errors in files you touched are.

## Committing

- Branch off `main`; CI (`.github/workflows/tests.yml`) runs `composer setup` then `composer ci:check` on every push.
- `composer ci:check` = `npm run check`, `npm run types:check`, Pint in test mode, PHPStan, then the Pest suite.
- Build output (`public/build`, `docs/.vitepress/dist`), Wayfinder output (`resources/js/actions|routes|wayfinder`) and `graphify-out/` are git-ignored or local only.

## Recording what you learned

Durable decisions and traps go into `.ai/rules` through the Boost `record-rule` tool (glob, title, note). That directory is shared with the team and with the coding agents; personal notes are not.
