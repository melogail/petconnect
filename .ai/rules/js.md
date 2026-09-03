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
Claim under test: the generated Wayfinder tree (~18.2 KB, the size quoted in the original report) is bundled whole into the entry chunk because it is a barrel of `Object.assign`-built objects, which defeats tree-shaking. **False. No change was made and `vite.config.ts` is byte-identical.**

Control experiment, **2026-09-02**: setting `moduleSideEffects: false` globally — the maximum tree-shaking Rollup can do — moved total assets by **~191 bytes**. There is no 18 KB of dead Wayfinder code to shake out.

`like` was in the entry chunk because it was a **live cross-chunk export**, not dead code: something in the entry graph imported the module, so Rollup had to keep the export reachable. What imported it has since changed, so the citation has moved with it.

**Re-measured 2026-09-03**, on this working tree, by running `npm run build` and then the greps below against `public/build/assets/`. The former entry-chunk importer was `PublicHeader.vue`, which imported `create` from `@/routes/pets`; the UI port removed the header's publish button and that import with it (`grep -rn '@/routes/pets' resources/js` now returns only a prose mention in that file). Every surviving importer — `components/pets/PetLikeButton.vue:6`, `CreatePetButton.vue:7`, `PetOwnerActions.vue:15`, the six `components/pets/card/*` files, `pages/Help.vue:11`, `pages/pets/Create.vue:8`, `pages/pets/Edit.vue:12` — is reached only through a lazily-resolved page chunk, so Rollup now emits `routes/pets` as its own shared chunk that the entry only *preloads*:

    grep -c '/pets/{pet}/like' public/build/assets/app-*.js    # 0  (was 1 on 2026-09-02)
    grep -c '/pets/{pet}/like' public/build/assets/pets-*.js   # 1

Measured the same day and tree: `assets/pets-BxNcfnql.js` is **5,556 bytes** and `assets/app-B9zopaHd.js` is **178,803 bytes**; `/pets/create` and `/pets/{pet}/edit` left the entry chunk alongside `like`. Hashed filenames change on every build — re-run the greps, do not trust the names.

**This does not reopen the finding; it corroborates it.** The module's presence in the entry chunk tracked its importer, which is exactly what "live code, not dead code" means. Tree-shaking was never the variable, and nothing was configured, tuned or fixed to move the number.

Methodological point, which is the part worth keeping: the acceptance criterion was set **in advance** — `grep -c '/pets/{pet}/like' public/build/assets/app-*.js` must reach 0. Under the experiment it stayed at **1** before and after, and that pre-committed number is what exposed the hypothesis as wrong; reporting it honestly instead of forcing it down is what closed the finding. The number is **0** today, reached by deleting a button — not by the change the hypothesis proposed. A pre-committed number is only readable against the date and the tree it was taken on, which is why both readings above carry theirs.
