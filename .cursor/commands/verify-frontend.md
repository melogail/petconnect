# Verify PetConnect Frontend

Use this command after Vue, TypeScript, Tailwind, Vite, Wayfinder import, layout, or component changes.

Target/change: `$ARGUMENTS`

## Steps

1. Identify changed frontend files and whether they affect route helpers, app boot, Tailwind, or shared UI.
2. Format resources when Vue/CSS changed:

```bash
npm run format
```

3. Run type checking for prop/import/composable changes:

```bash
npx vue-tsc --noEmit
```

4. Run production build for broad UI, Vite, Tailwind, route helper, or app entry changes:

```bash
npm run build
```

5. If backend routes changed, regenerate Wayfinder first:

```bash
php artisan wayfinder:generate --with-form --no-interaction
```

## Report

Return exact commands run, pass/fail result, and any unchecked browser/responsive risk.
