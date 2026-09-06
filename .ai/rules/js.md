---
paths:
  - 'resources/js/**'
  - vite.config.ts
---

# Js

> **Rendered behaviour in this directory IS observable — no dependency needed.** There is no
> `playwright`, `vitest` or `jsdom` in `package.json`, and that is a fact about the tooling, not a
> verdict on the task. A build in an isolated copy of the tree plus Chrome over CDP has already
> clicked feed controls through to real database rows and measured a missing `maxlength`. The
> recipe, its two working limits (synthetic input, and reka-ui `Select`s) and the `public/hot` trap
> that makes the attempt look broken live in `.ai/rules/general.md`, "Amplification toward
> inaction: absence of a tool is evidence about the tool, not about the task". This is a pointer,
> not a copy.

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

**Re-measured 2026-09-03**, on this working tree, by running `npm run build` and then the greps below against `public/build/assets/`. The former entry-chunk importer was `PublicHeader.vue`, which imported `create` from `@/routes/pets`; the UI port removed the header's publish button and that import with it (`grep -rn '@/routes/pets' resources/js` now returns only a prose mention in that file). Every surviving importer — `components/pets/PetLikeButton.vue`, `CreatePetButton.vue`, `PetOwnerActions.vue`, the six `components/pets/card/*` files, `pages/Help.vue`, `pages/pets/Create.vue`, `pages/pets/Edit.vue` — is reached only through a lazily-resolved page chunk, so Rollup now emits `routes/pets` as its own shared chunk that the entry only *preloads*:

    grep -c '/pets/{pet}/like' public/build/assets/app-*.js    # 0  (was 1 on 2026-09-02)
    grep -c '/pets/{pet}/like' public/build/assets/pets-*.js   # 1

Measured the same day and tree: `assets/pets-BxNcfnql.js` is **5,556 bytes** and `assets/app-B9zopaHd.js` is **178,803 bytes**; `/pets/create` and `/pets/{pet}/edit` left the entry chunk alongside `like`. Hashed filenames change on every build — re-run the greps, do not trust the names.

**Importer list re-checked 2026-09-06** with `grep -rln '@/routes/pets' resources/js`, without re-running the build. Same files, one count moved: `components/pets/card/` now holds **five** importers, not the six measured on 2026-09-03 — `card/PetCardCommentLink.vue` was deleted by the UI port since. The 2026-09-03 figure is left standing above because it is what was measured that day against that tree; this is the re-check beside it, not a renumbering of it. Line numbers were also dropped from the importer list on 2026-09-06: `PetOwnerActions.vue:15` had already drifted to `:16`, and a file path alone identifies an importer, so the number was pure drift surface with nothing to buy. See "A claim must say how it was established" in `.ai/rules/general.md` on preferring file + identifier over `file:line`.

**This does not reopen the finding; it corroborates it.** The module's presence in the entry chunk tracked its importer, which is exactly what "live code, not dead code" means. Tree-shaking was never the variable, and nothing was configured, tuned or fixed to move the number.

Methodological point, which is the part worth keeping: the acceptance criterion was set **in advance** — `grep -c '/pets/{pet}/like' public/build/assets/app-*.js` must reach 0. Under the experiment it stayed at **1** before and after, and that pre-committed number is what exposed the hypothesis as wrong; reporting it honestly instead of forcing it down is what closed the finding. The number is **0** today, reached by deleting a button — not by the change the hypothesis proposed. A pre-committed number is only readable against the date and the tree it was taken on, which is why both readings above carry theirs.

## Entry-chunk reading, 2026-09-03: the phase 5 header cost +19,466 B (+2.82 kB gzipped)

Same method as above, both readings taken on **2026-09-03** by running `npm run build` and reading vite's own asset table plus `ls -l` on `public/build/assets/`:

- HEAD `2ed96da`, built in a clean `git worktree` of that commit with `node_modules` and `vendor` symlinked in: `assets/app-BQXtFtvM.js` = **178,844 B** (vite: 51.02 kB gzipped).
- The phase 5 working tree, same day, same machine: `assets/app-BiZ5yavI.js` = **198,310 B** (vite: 53.84 kB gzipped).
- Delta: **+19,466 B raw, +2.82 kB gzipped.**

What moved it is the phase's header work as a whole — the messages menu, its rows, skeleton and badge, the notification inbox actions — plus the Reka `Popover` primitive they pull in, and it lands in the **entry** chunk rather than a page chunk because both headers, `PublicHeader` and `AppSidebarHeader`, are in the entry graph, so everything they mount is. The primitive is the one part attributable on its own: `grep -c 'reka-popover-content-transform-origin' public/build/assets/app-*.js` returns **1** on the phase 5 tree and **0** at `2ed96da` (0 across every asset there, not just the entry chunk). The rest of the delta is not itemised — it was not measured component by component. That is the same "live cross-chunk export, not dead code" mechanic the section above closes on: the primitive is in the entry chunk because something in the entry graph mounts it, and no tree-shaking setting will move it while that stays true.

Not a defect and nothing to tune — a header control that must render before the first interaction cannot be lazily chunked. It is written down because this file makes entry-chunk bytes a tracked number, and a number without its date and its file paths cannot be re-checked.

Scaffolding `components/ui/popover` during the same phase cost nothing measurable on its own: the wrapper re-points imports at primitives the bundle already contained. Between the pre-review phase 5 tree (`app-B1rlGx3I.js`, **196,721 B**, still on disk when the fixes started) and the tree above, the review fixes as a whole — the wrapper, the identity-keyed caches and `markConversationRead` — added **1,589 B** raw.

## PetFormStepper is deliberately plain: the legacy treatment was debt its own authors flagged
Settled, and recorded with its reason so it is not reopened as an oversight: the legacy pet form's per-step gradient, pulse, bounce and scale transform — eight colour schemes and fifteen animations for a progress bar — are **not** ported. `resources/js/components/pets/form/PetFormStepper.vue:15-17` carries the same note at the call site.

The reason is the load-bearing part. The legacy notes themselves called that treatment styling debt. **Matching the old UI does not extend to reproducing what its own authors flagged as debt** — parity is with what legacy meant to ship, not with everything it happened to contain. Anything else in the port that legacy's own notes disown gets the same treatment.

A settled decision recorded with its argument survives a reader who disagrees with it; a bare "we chose not to" reads as an oversight and gets reopened.

Cross-reference, not a copy: a capability can also be lost by *widening* a contract rather than deleting code, and a Vue prop that gains a `?` is the commonest shape of it in this directory. The check, its operationalisation and the measured instance (a comments dialog mounted from a feed card receiving `undefined` for both report vocabularies) are in `.ai/rules/general.md`, "Review what a change REMOVED — and what it merely made optional". Read it before closing out a diff that touches prop declarations.
