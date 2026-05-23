# Implement A PetConnect Change

Use this command to implement a feature, bug fix, or behavior change in this PetConnect Laravel/Inertia/Vue project.

Target request: `$ARGUMENTS`

## Workflow

1. Read `.cursor/rules/00-petconnect-project.mdc` and any relevant rules in `.cursor/rules/`.
2. Inspect the affected files before planning. Use `rg --files`, `rg`, and sibling files first.
3. Read the relevant skills from `.cursor/skills/`:
   - Backend PHP: `laravel-best-practices`, `laravel-security`, `laravel-repositories`, `laravel-api-resources`, `petconnect-domain-development`, `spatie-media-library-development`, `nova-admin-development` as applicable.
   - Frontend Vue/Tailwind: `inertia-vue-development`, `tailwindcss-development`, `wayfinder-development`.
   - Auth: `fortify-development`.
   - Tests: `laravel-testing`, `pest-testing`.
4. Implement in dependency order: backend contract first, then frontend, then tests/verification.
5. Preserve existing patterns. Do not add dependencies or top-level folders without approval.

## Verification Gate

Pick the smallest useful checks:

- PHP changed: `vendor/bin/pint` and focused `php artisan test`.
- Routes/controllers changed and Vue imports helpers: `php artisan wayfinder:generate --with-form --no-interaction`.
- Vue/TS changed: `npm run format` and `npx vue-tsc --noEmit` or `npm run build`.
- Broad behavior changed: run the relevant feature tests and consider full `php artisan test`.

## Final Response

Report files changed, behavior changed, verification run, and any checks not run.
