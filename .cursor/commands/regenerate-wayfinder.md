# Regenerate PetConnect Wayfinder Helpers

Use this command after route or controller changes that Vue code consumes.

Target/change: `$ARGUMENTS`

## Steps

1. Inspect route/controller changes and confirm whether `resources/js/actions` or `resources/js/routes` should change.
2. Regenerate helpers:

```bash
php artisan wayfinder:generate --with-form --no-interaction
```

3. Check changed generated files:

```bash
git diff -- resources/js/actions resources/js/routes
```

4. Run a frontend verification command if imports or helper signatures changed:

```bash
npx vue-tsc --noEmit
```

or

```bash
npm run build
```

## Report

Summarize generated helper changes and verification status.
