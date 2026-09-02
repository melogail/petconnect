---
paths:
  - composer.json
  - composer.lock
  - '**/*.php'
  - '**'
  - vite.config.ts
---

# General

## Laravel 13 dependency floors: medialibrary 11.23.5, Nova 5.8, ebess 5.2
Version floors worked out during the Laravel 13 port — do not "correct" them.

- `spatie/laravel-medialibrary`: **v12 does not exist**. 11.23.5 is the first release accepting `illuminate/* ^13.0` (11.20.0 still caps at `^12.0`). Require `^11.23.5`, not `^12`.
- `laravel/nova`: needs **>= 5.8.0** for Laravel 13. Nova 5.0–5.7.x cap at `laravel/framework ^12.1.1`. Nova also requires the `https://nova.laravel.com` composer repository declared in `composer.json`, with licence credentials in `~/.config/composer/auth.json` (never committed).
- `ebess/advanced-nova-media-library`: **5.2** is the first Laravel 13-compatible release. Known future blocker: it still pins `spatie/laravel-medialibrary ^8 – ^11`, so the day medialibrary ships v12 this package will block the upgrade until it widens the constraint.

## Run Pint with explicit paths, not --dirty, when more than one agent is working
`CLAUDE.md` mandates `vendor/bin/pint --dirty --format agent`. That is sound for a single agent and **wrong for a fleet**: `--dirty` formats every uncommitted file in the tree, not the files the running agent touched. This divergence from CLAUDE.md is deliberate — do not "correct" it back.

Measured this phase: an agent working only in `database/migrations/**` ran `pint --dirty` and applied a `phpdoc_align` change to `app/Actions/Comments/ListCommentSubtreeIds.php`, a file a *different* agent was mid-edit on. That instance was formatting-only; the dangerous versions are an `Edit` failing because content shifted underneath it, or a half-written file being reformatted into something that parses but is not what the author meant.

Rule: when more than one agent may be working concurrently, run `vendor/bin/pint --format agent path/one.php path/two.php` with explicit paths. `--dirty` is fine only for a single agent working alone.

## PHPStan works and is red: 145 pre-existing errors, a backlog item and not a broken tool
`composer ci:check` was already failing before Phase 6 began, and it still is, because the analyser finds real errors — not because it cannot run. Do not read the failure as a regression from this phase, and do not read it as a broken toolchain.

**Corrected 2026-09-02, by running it.** This section used to claim that on this branch `vendor/bin/phpstan analyse` (and `composer types:check`, same binary) "does not complete an analysis at all — it aborts with `Undefined constant \"Larastan\\Larastan\\LARAVEL_VERSION\"` from LarastanStubFilesExtension.php:25 and exits 1 without reporting a single error", and that `composer ci:check` therefore passed the type gate without analysing anything. **That is false.** It was inferred from version numbers and vendor source, never executed, and it was self-sealing: it told every subsequent agent the analyser was broken, so nobody ran the tool that would have disproved it, and it reached the user twice as fact.

Measured: `vendor/bin/phpstan analyse --no-progress` runs correctly at level 7 over `app/`, `bootstrap/app.php`, `config/`, `database/`, `routes/` with **no baseline** in `phpstan.neon`, and reports **145 errors** across `app/`. Narrowed, `vendor/bin/phpstan analyse app/Nova/Actions` reports 2, both pre-existing at HEAD. No abort, no undefined constant, no zero-error false pass. larastan/larastan 3.10.0 against laravel/framework 13.29.0 analyses this codebase fine.

The findings are substantive Laravel-generics work, not noise: undefined `withLikedBy()` on `Relation`; `MorphMany` child-return-type incompatibilities across the `HasComments` / `HasLikes` / `HasReport` concerns; `Admin|User|null` passed where `User` is required in `ProfileController`; undefined `passkeys()` on the `Admin|User` union in `SecurityController`. **None** were introduced by the Laravel 13 upgrade — they predate it and were deliberately deferred as separate work.

So: this is a backlog item with a known, reproducible error list, and the first question when you pick it up is the error list itself — read the 145, decide which are real type bugs and which want a generics annotation, and fix or baseline deliberately. It is not a dependency-compatibility problem, and there is nothing to debug in the phar.

## A rule's glob must cover where the mistake gets made, not where the fix lives
When you record a rule, the glob decides who ever reads it. Scope it to the directories where the mistake gets *written*, not the directory the correct implementation now sits in — those are usually different, and the difference is invisible at the moment of recording, because at that moment you are looking at the fix.

Worked example, cost a live bug: the "walk a comment subtree with ListCommentSubtreeIds, never a frontier loop" rule was filed under `app/Actions/Comments/**` — the home of the Action that *is* the fix. Frontier loops are written in `app/Pipelines/**`, which that glob does not match. A third loop in `app/Pipelines/Pets/Purge/` survived two rounds of the fix and, unlike the two that were replaced, had no cycle guard at all: it spun forever inside an open transaction holding locks. Nobody editing that file was ever shown the rule.

Prefer the widest glob whose files could plausibly contain the mistake, and prefer a wildcard over enumerated directories (`app/Pipelines/**` over `app/Pipelines/Comments/**` + `app/Pipelines/Pets/Purge/**`) — an enumeration re-creates the same gap the moment a fourth flow gets its own directory. If a rule extends an existing section, record it into that section's file so the two stay together.

Second instance this phase of rule *reach* rather than rule *content* being the defect; the first was `index.md`'s preamble telling agents to read only the first matching file. Both were silent: the rule existed, was correct, and was never delivered.

## Establish runtime behaviour by running it, not by reading the source
Four false claims in one close-out shared one root: predicting runtime behaviour instead of observing it. Reading a dependency's source tells you what it *should* do; only running it establishes what it *does*.

- larastan was declared broken from version numbers and vendor source. Running `vendor/bin/phpstan analyse --no-progress` shows it works and reports 145 real errors.
- SSR was declared broken by reading `@inertiajs/vite`'s resolver and reasoning that the `app.ts` fallback exports nothing and must throw. `npm run build:ssr` exits 0 and emits `bootstrap/ssr/app.js` — Inertia v3's simplified SSR has the Vite plugin handle the entry itself.

Second half of the same rule: absence of the artefact you searched for is not absence of the thing itself. A grep for `DB::transaction` without a `catch` structurally could not find the action that had no transaction at all — the most dangerous one. A coverage check that grepped for a test file named after an Action concluded "no coverage" when the coverage lived in a policy test. Enumerate the behaviour and ask what covers it, rather than searching for the shape you expect to find.

## Vite watcher ignores extend the defaults; count directories, not files
Three settled points about `server.watch.ignored`, from Phase 1.

**inotify allocates one watch per directory, not per file.** File counts are the wrong metric and produced two wrong diagnoses this phase. When you size a watch list, count directories.

**Vite prepends its own defaults — the user array extends, it does not replace.** `resolveChokidarOptions` spreads the user array *after* `.git`, `node_modules`, `test-results` and the cache dir, so anything already on that default list is redundant in `server.watch.ignored`. This was established **by reading the installed Vite source**, not by measurement — weigh it as such, and if it ever matters more than it does now, run it.

**The numbers, each named against what it was measured on.** `storage/app/public/media` is **17,878 directories** and is the entry that actually matters. `vendor` (**2,529 directories**) is real work because it is *not* a Vite default. `public` (**6 directories**) is not worth the reload-on-change it costs.

## A claim must say how it was established
Twelve defects in one phase shared a single root: **a claim and its consumer drifting apart, with nothing in the build failing when they disagree.**

The instances: a config file whose prose said it was live while the build ignored it; a CSS comment whose hex and HSL disagreed with each other; a "violet-500 cannot carry text" warning contradicted by a component three files away; a contrast ratio measured against a reference the token never uses; a green presence dot for a state the schema deliberately does not track; an agent-instruction file that was wrong about its own build; and one where **no comment existed at all**, which let a wrong model survive two readers.

Five of the twelve were caught by **measurement** rather than by reading. Three had already been written down correctly **elsewhere in the same repo**.

Standing obligation: **every number names the pair it was measured against, and every claim about the build cites the file that proves it.** A claim that says how it was established can be re-checked when its consumer moves; one that does not, silently rots.
