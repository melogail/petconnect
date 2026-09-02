---
paths:
  - 'resources/js/**'
  - vite.config.ts
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

## Regenerating Wayfinder by hand needs --with-form, or it silently drops the form variants
`vite.config.ts` configures `wayfinder({ formVariants: true })`, so the build emits a `.form` helper next to every route/action function. A bare `php artisan wayfinder:generate` does **not** read `vite.config.ts` — form variants are opt-in per invocation — so it overwrites `resources/js/{actions,routes}/**` with a tree that has no `.form` on anything.

Nothing fails at generation time. It surfaces later and somewhere else: `npm run types:check` reports `TS2339: Property 'form' does not exist` at every existing `v-bind="store.form()"` call site (15 of them, all pre-existing scaffold pages such as `resources/js/pages/auth/Login.vue`), which reads like the *pages* are broken. They are not; the generated tree is.

Regenerate with `php artisan wayfinder:generate --with-form --no-interaction`, or just run `npm run build` and let the vite plugin do it with the configured options. Then confirm with `npm run types:check`.

`resources/js/{actions,routes,wayfinder}/**` is generated output: regenerate it, never hand-edit it, and never "fix" a `.form` error by changing the `.vue` call site.

## The Wayfinder bundle-bloat finding is a false positive — closed by disproof, do not reopen
Claim under test: the generated Wayfinder tree (~18.2 KB) is bundled whole into the entry chunk because it is a barrel of `Object.assign`-built objects, which defeats tree-shaking. **False. No change was made and `vite.config.ts` is byte-identical.**

Control experiment: setting `moduleSideEffects: false` globally — the maximum tree-shaking Rollup can do — moved total assets by **~191 bytes**. There is no 18 KB of dead Wayfinder code to shake out.

The real reason `like` appears in the entry chunk is that it is a **live cross-chunk export**, not dead code: `resources/js/components/PublicHeader.vue:17` imports `create` from `@/routes/pets`, which pulls `routes/pets/index.ts` into the entry chunk; `PetLikeButton.vue` then imports `like` from that same module in a page chunk. Rollup must keep the export reachable. Nothing to fix.

Methodological point, which is the part worth keeping: the acceptance criterion was set **in advance** — `grep -c '/pets/{pet}/like' public/build/assets/app-*.js` must reach 0. It stayed at 1 before and after. That pre-committed number is what exposed the hypothesis as wrong; reporting it honestly instead of forcing it down is what closed the finding correctly. Set the number before the experiment, not after.
