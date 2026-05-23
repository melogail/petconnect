---
name: inertia-vue-development
description: "Use for PetConnect Inertia/Vue work: pages, components, forms, route helpers, shared props, auth/settings screens, pet wizard pages, messaging views, infinite scroll, flash toasts, and frontend data contracts."
license: MIT
metadata:
  author: petconnect
---

# Inertia Vue Development For PetConnect

## Project Setup

- Inertia pages live in `resources/js/pages`.
- `resources/js/app.ts` resolves pages with `./pages/${name}.vue`.
- Use `@/*` imports for `resources/js/*`.
- Prefer `<script setup lang="ts">` for new Vue files.
- Reuse `MainLayout`, auth layouts, settings layouts, domain components, UI primitives, and composables before creating new files.

## Navigation And Routes

- Use Inertia `<Link>` or `router` for page visits.
- Do not use Vue Router for Inertia pages.
- Prefer Wayfinder generated imports for new typed auth/settings-like links and forms.
- Preserve existing Ziggy `route()` usage in domain files unless intentionally converting.
- Never hardcode a URL when a route helper exists.

## Forms

- Use `<Form v-bind="Controller.action.form()">` for simple forms with generated Wayfinder actions.
- Use `useForm` for pet wizard, profile edit, messaging, comments, reviews, reports, image uploads, and transform-heavy forms.
- Keep upload preview fields out of final payloads.
- Use existing `InputError`, field components, dialog components, and toast patterns.

## Shared Props

Shared props: `auth.user`, `flash`, `messaging`, `name`, `quote`, `sidebarOpen`.

Composables to check first: `useAuthUser`, `useFlashToast`, `useAppearance`, `useTwoFactorAuth`, `useInertiaInfiniteScroll`.

## Verification

- `npm run format` for resources formatting.
- `npx vue-tsc --noEmit` for type checks.
- `npm run build` for route imports, Tailwind, Vite, or broad UI changes.
