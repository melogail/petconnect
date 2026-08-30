---
paths:
  - 'resources/js/**'
---

# Js

## Extract Vue components early, keep pages simple
Pages in `resources/js/pages/` compose components; they do not hold markup blocks or logic that could live in a component.

- Reuse `resources/js/components/ui/**` before writing anything new; extract a shared component the moment a block of markup appears twice or a page grows past roughly 150 lines.
- One component, one job. Props in, events out — no cross-component state juggling.
- Prefer the simplest thing that works: `<Form>` / `useForm` over hand-rolled submit handlers, Wayfinder route helpers from `@/actions` and `@/routes` over hardcoded URLs.
