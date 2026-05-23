---
name: nova-admin-development
description: "Use for PetConnect Laravel Nova work: Nova resources for users, pets, categories, breeds, dashboards, fields, actions, filters, lenses, authorization, Nova Fortify settings, and Advanced Nova Media Library integration."
license: MIT
metadata:
  author: petconnect
---

# Nova Admin For PetConnect

## Current Setup

- Nova resources live in `app/Nova`.
- Current resources include `User`, `Pet`, `Category`, and `Breed`.
- `NovaServiceProvider` registers Nova routes, dashboard, Fortify settings, and `viewNova` gate.
- The non-local `viewNova` allow-list is currently empty.

## Rules

- Keep admin fields explicit and relevant.
- Use Nova validation rules for admin forms.
- Add filters/lenses/actions only for real admin workflows.
- Do not expose Nova publicly.
- Reuse policies when appropriate, but remember Nova access is controlled separately by `viewNova`.

## Verification

- Run `php artisan route:list` for Nova routing/auth changes.
- Run focused tests if access gates or model policies changed.
