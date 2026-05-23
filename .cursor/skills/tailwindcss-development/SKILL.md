---
name: tailwindcss-development
description: "Use when styling PetConnect Vue or Blade UI with Tailwind CSS, including pet cards, forms, filters, sheets, dialogs, navbars, messaging, auth/settings screens, responsive layouts, dark mode, and Tailwind v4 app.css changes."
license: MIT
metadata:
  author: petconnect
---

# Tailwind CSS For PetConnect

## Version And Config

PetConnect uses Tailwind CSS v4 with CSS-first configuration in `resources/css/app.css`.

- Use `@import 'tailwindcss';`.
- Do not replace this with v3 `@tailwind` directives.
- `tailwind.config.js` exists for compatibility and primary color extension; do not remove or rewrite it without a specific reason.
- Dark mode uses `.dark` and `dark:` variants.

## Tokens

Prefer semantic variables/classes:

- `bg-background`, `text-foreground`
- `bg-card`, `text-card-foreground`
- `bg-muted`, `text-muted-foreground`
- `border-border`, `ring-ring`
- `bg-primary`, `text-primary`, `bg-accent`

## UI Rules

- Reuse shadcn-vue/reka-ui primitives from `components/ui`.
- Use `Button`, `Input`, `Label`, `Dialog`, `Sheet`, `DropdownMenu`, `Tooltip`, `Card`, `Skeleton`, and `Alert` before custom controls.
- Use lucide-vue-next icons for action buttons.
- Use gap utilities for spacing.
- Keep responsive behavior for mobile pet forms, filters, cards, and messaging views.
- Preserve clear hover, focus, loading, disabled, empty, and error states.

## Verification

- Check mobile and desktop layouts.
- Check dark mode for shared components.
- Run `npm run build` for broad styling changes.
