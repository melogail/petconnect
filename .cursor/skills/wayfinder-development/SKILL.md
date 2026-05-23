---
name: wayfinder-development
description: "Use when PetConnect frontend code calls Laravel routes/controllers with generated Wayfinder helpers, imports from resources/js/actions or resources/js/routes, backend route/controller changes affect Vue, route-related TypeScript errors appear, or helpers need regeneration."
license: MIT
metadata:
  author: petconnect
---

# Wayfinder For PetConnect

## Current Setup

`vite.config.js` registers `@laravel/vite-plugin-wayfinder` with `formVariants: true`.

Generated files live in:

- `resources/js/actions`
- `resources/js/routes`

Do not edit generated files by hand.

## Usage

- Use generated controller action imports for auth/settings-style forms.
- Use generated route imports for typed links when available.
- Preserve Ziggy `route()` in existing domain files unless converting the whole local flow.
- Avoid hardcoded URLs.

## Regenerate

After changing backend routes or controller actions used by Vue:

```bash
php artisan wayfinder:generate --with-form --no-interaction
```

Then run type/build checks if imports changed.

## Pitfalls

- Do not mix manual `action`/`method` with a generated `.form()` object on the same `<Form>`.
- Pass route parameters in the shape generated helpers expect, often `{ pet: id }` or an object with `id`.
- Regenerate after route names, URI params, controller method names, or HTTP verbs change.
