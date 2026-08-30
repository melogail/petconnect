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

## An empty array or object never reaches the server on a form that uploads a file
Inertia serialises to FormData as soon as the payload contains a File, and `objectToFormData` appends nothing at all for `[]` or `{}`. `null` survives (it is appended as `''`, then ConvertEmptyStringsToNull turns it back into null). So on any form with a file input, an empty repeater, an empty tag list or an empty coordinate object arrives as a missing key, not as an empty one.

Two obligations follow. Send `null`, not `[]` or `{}`, when the intent is "explicitly cleared" and the backend distinguishes the two. And do not assume a backend `present` rule can be satisfied by an empty collection — it cannot; the backend rules deliberately omit `present` on collection keys for this reason (see .ai/rules/requests.md).

The pet create form is the live case: it always posts a required `featuredImage`, so it is always multipart, and a listing with no traits/vaccinations/medications/allergies/extras/map pin sends six fewer keys than the same listing edited without a new photo.
