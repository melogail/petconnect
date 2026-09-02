---
paths:
  - composer.json
  - composer.lock
  - '**/*.php'
  - '**'
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

## PHPStan is red and predates Phase 6 — separate work, deliberately not absorbed
`composer ci:check` was already failing before Phase 6 began, and it still is. Do not read that as a regression this phase introduced.

State of it, level 7 over `app/`, `bootstrap/app.php`, `config/`, `database/`, `routes/`, with **no baseline** in `phpstan.neon`: earlier agents this phase counted 12 errors in a narrow scope and ~144 on a full run. **None** of them are in any file touched during Phase 6.

Re-verified at the end of the phase and the picture is now worse, not better: on this branch `vendor/bin/phpstan analyse` (and `composer types:check`, same binary) does not complete an analysis at all — it aborts with `Undefined constant "Larastan\Larastan\LARAVEL_VERSION"` from LarastanStubFilesExtension.php:25 and exits 1 without reporting a single error. larastan/larastan 3.10.0 against laravel/framework 13.29.0; larastan 3.x does not claim Laravel 13. So the ~144 figure is a *previous* measurement, not something you can reproduce today.

Phase 6's success criteria were, deliberately and in full: the Pest suite, `npm run build`, `vue-tsc`, and Pint on the touched files. PHPStan was excluded on purpose — fixing 144 errors with no baseline, on a toolchain whose analyser will not start, is its own piece of work with its own risk, not a side quest inside a rate-limit sweep.

If you pick it up: the first question is the larastan/Laravel 13 compatibility bump, not the error list. Do not sink time debugging the phar — one agent burned 17 minutes on it. Either the dependency moves (needs approval) or a baseline is generated once the analyser runs again.

## A rule's glob must cover where the mistake gets made, not where the fix lives
When you record a rule, the glob decides who ever reads it. Scope it to the directories where the mistake gets *written*, not the directory the correct implementation now sits in — those are usually different, and the difference is invisible at the moment of recording, because at that moment you are looking at the fix.

Worked example, cost a live bug: the "walk a comment subtree with ListCommentSubtreeIds, never a frontier loop" rule was filed under `app/Actions/Comments/**` — the home of the Action that *is* the fix. Frontier loops are written in `app/Pipelines/**`, which that glob does not match. A third loop in `app/Pipelines/Pets/Purge/` survived two rounds of the fix and, unlike the two that were replaced, had no cycle guard at all: it spun forever inside an open transaction holding locks. Nobody editing that file was ever shown the rule.

Prefer the widest glob whose files could plausibly contain the mistake, and prefer a wildcard over enumerated directories (`app/Pipelines/**` over `app/Pipelines/Comments/**` + `app/Pipelines/Pets/Purge/**`) — an enumeration re-creates the same gap the moment a fourth flow gets its own directory. If a rule extends an existing section, record it into that section's file so the two stay together.

Second instance this phase of rule *reach* rather than rule *content* being the defect; the first was `index.md`'s preamble telling agents to read only the first matching file. Both were silent: the rule existed, was correct, and was never delivered.
