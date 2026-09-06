---
paths:
  - 'database/migrations/**'
---

# Migrations

## RESTRICT + soft deletes: deleting a category fails even when pets look deleted
`pets.category_id` is `restrictOnDelete` and `pets` uses `softDeletes()`. A soft-deleted pet still holds its `category_id` row, so deleting a Category (in Nova or anywhere) throws a raw DB foreign-key constraint error even when every visible pet has been "deleted". Phase 3 must add an explicit guard (block the delete, or force-delete/reassign trashed pets first) and surface a friendly message instead of the driver exception. The same trap applies to any future RESTRICT FK pointing at a soft-deleting table.

## The database is MySQL in development and SQLite under test — check before you reason about a plan
**Corrected 2026-08-31.** This file used to open "This project runs SQLite (`DB_CONNECTION=sqlite` in `.env`, `.env.example`, `phpunit.xml`)". That is wrong and it was the stated justification for every index decision in this port. Measured: `.env` is `DB_CONNECTION=mysql` and `php artisan db:show` reports **MySQL 8.0.46**, database `petconnect`. `.env.example`, `phpunit.xml` and `.env.testing` (added 2026-09-02, see the `--env=testing` note below) are the SQLite ones — so the split is **MySQL in dev (and therefore whatever prod becomes), SQLite in memory under test**.

Consequences to keep in mind: an `EXPLAIN QUERY PLAN` measurement proves nothing about the driver the application actually runs on, and the two optimisers differ in exactly the place index work lives (SQLite will happily `USE TEMP B-TREE FOR ORDER BY` where InnoDB reaches for a second index and a backward scan). Measure a plan on both, or say which one you measured. `database/database.sqlite` is still in the tree and is *not* the dev database; it is a leftover from before the MySQL switch and should be deleted once someone confirms no local checkout points at it.

## Index foreign-key columns explicitly: neither driver leaves you with a free FK index you can rely on
SQLite does NOT auto-create an index for a foreign key, so `foreignId()->constrained()` leaves the column unindexed there — which matters because the whole test suite runs on it. Declare `$table->index('<fk_column>')` explicitly for every FK you actually filter or join on. A FK that is already the leading column of a composite or unique index (e.g. `conversation_user.conversation_id`) needs no separate index. Do not maintain a list of which columns are covered here — read the migration.

MySQL/InnoDB does NOT make these explicit indexes redundant. InnoDB creates its own FK index only when no suitable index already exists; because these `index()` calls sit in the same Blueprint, InnoDB adopts them instead of adding its own. They are not duplicates. Keep them — dropping them because "InnoDB indexes FKs anyway" just makes InnoDB silently recreate them under generated names.

## A `status`-style filter plus an ordering column wants one composite index, not two single ones
`pets` carries `(status, deleted_at, created_at)` as `pets_status_deleted_at_created_at_index`, and the single-column `status` index was **removed** when it was added — it is a strict prefix of the composite, so it narrowed nothing and only cost writes.

Why: the home feed is `where status = ? and deleted_at is null order by created_at desc limit 12`, `status = 'available'` is deliberately low-selectivity (~94% of rows), and `softDeletes()` puts `deleted_at is null` on every `pets` query while the table had **no `deleted_at` index at all**. On SQLite at 57k available rows that was `SEARCH USING pets_status_index` + `USE TEMP B-TREE FOR ORDER BY`, 49 ms to return 12 rows. On MySQL 8 the old shape did not filesort — the optimiser walked `pets_created_at_index` backwards (`type: index`) and filtered as it went, which is fine at 94% selectivity and degrades to a full index scan the moment the filter is selective. With the composite, MySQL reports `type: ref`, `ref: const,const`, `Backward index scan`, no filesort, and the paginator's `count(*)` becomes `Using index` — covered, no table access.

Generalise it: when a query pins columns with equality and then orders, the index has to span the equality columns *and* the ordering column in that order. And when a table soft-deletes, `deleted_at` belongs in that composite because the framework adds the predicate to every query whether you wrote it or not.

## $table->id() emits AUTOINCREMENT on SQLite, so ids are NOT recycled here
Verified against the **test** schema — `sqlite_master` for `comments`, `reviews`, `reports`; the dev database is MySQL, see the correction at the top of this file — where `$table->id()` produces `integer primary key autoincrement`, and AUTOINCREMENT is exactly what stops SQLite reusing a deleted row's id. So the premise recorded in .ai/rules/pipelines.md — "a stranded polymorphic child eventually collides with a genuine row on a recycled id (SQLite hands out max(id)+1 without AUTOINCREMENT; InnoDB's counter does not survive a restart)" — does not hold on this schema. The MySQL half was only ever true before InnoDB 8.0, which persists the counter.

What still stands, and is reason enough on its own: a morph column carries no FK, so deleting a parent strands its polymorphic children (likes, reports) silently, and those rows sit in a moderation queue resolving to null. Keep deleting them explicitly (Actions\Comments\DeleteComment, Actions\Reviews\DeleteReview). Keep converting the unique-index violation on `reports`/`reviews` to a ValidationException too — but justify it by the check-then-write race, not by id recycling. Re-check `sqlite_master` before repeating either claim.

## pets_created_at_index was reviewed and deliberately kept
Phase index review flagged `pets_created_at_index` (bare `created_at`) as possibly dead once `pets_status_deleted_at_created_at_index` and `pets_user_id_deleted_at_created_at_index` exist — every current ordered pets query pins an equality column first, so it is not a prefix of either and no query today reaches it as a leading column.

It was **kept on purpose**. It is one unqualified `orderBy('created_at')` — an unfiltered "newest listings" endpoint, a sitemap, a Nova default sort — away from being the only index that serves the page, and a single-column index on an already-written timestamp is cheap. The confidence that it is dead was lower than for the indexes that were removed, and removing a cheap index on a maybe is the wrong trade.

Do not re-discover it as dead weight. What would settle it: `EXPLAIN` (MySQL 8, the dev/prod driver) every pets query that has an `ORDER BY created_at` and confirm none reports `key: pets_created_at_index`, *and* confirm no unfiltered listing endpoint is planned. Only then drop it.

## Two index questions left open on purpose, and the measurement that settles each
Both were raised during the index review and **not acted on, because neither was measured**. Do not add either index on reasoning alone; take the measurement first, on MySQL 8 (`.env` dev driver), not on the SQLite test connection.

1. **A composite for the filtered pet feed.** The feed can filter on `category_id`, `breed_id`, `listing_type` and price/location on top of `status` + `deleted_at`, and today each of those is its own single-column index, so a filtered feed gets one index and filters the rest. A composite might help — but its column order depends entirely on which filter combinations users actually send and how selective they are, and guessing that produces an index that serves no real query. **Settles it:** log the real `where` combinations that arrive at the feed endpoint over a representative period, take the top one or two, then `EXPLAIN` those exact queries at production-ish row counts and compare `rows`/`Extra` with and without the candidate index.

2. **`users.created_at` for the metrics/Nova trend scans.** Nova value/trend metrics scan `users` by `created_at` range and the column is unindexed. Plausibly a full table scan, plausibly irrelevant — `users` is small, the metrics are cached, and admin pages are not a hot path. **Settles it:** `EXPLAIN` the metric's actual query at the real `users` row count and check for `type: ALL`; add the index only if it is a full scan *and* the metric is slow enough to notice.

## $table->morphs() ships an index — write the columns out by hand when a composite supersedes it
`morphs()` / `numericMorphs()` declares `(<name>_type, <name>_id)` as an index of its own. On `comments` and `notifications` that pair is now a **strict prefix** of the composites that serve the real queries (`comments_commentable_parent_created_at_index`; `notifications_notifiable_created_at_index` and `notifications_notifiable_read_at_index`), so it narrows nothing and only costs a write per insert — the same reasoning that removed `pets_status_index`.

There is no `morphs()` variant that skips the index, so both migrations declare `$table->string('<name>_type'); $table->unsignedBigInteger('<name>_id');` instead. That is the exact expansion of `numericMorphs()` minus the `index()` call, and it is deliberate. Do not "tidy" it back to `$table->morphs(...)` — that silently reinstates the redundant index. If `Builder::$defaultMorphKeyType` is ever changed to uuid/ulid, these two hand-written pairs must be changed with it (nothing sets it today).

`notifications` is the framework's own table with a UUID string primary key; it is edited in place like the rest of this unreleased schema.

## --env=testing now resolves to in-memory SQLite, because `.env.testing` exists
**Corrected 2026-09-02.** This section used to open "there is **no `.env.testing`** in this project (only `.env` and `.env.example` exist at root)" and conclude that `php artisan migrate:fresh --env=testing` **drops every table in the live MySQL dev database**. Both sentences are now false and neither should be repeated: `.env.testing` was committed in `7125ba2` precisely to close that hole.

Measured state: `.env.testing` is at the project root, tracked, and pins `DB_CONNECTION=sqlite` / `DB_DATABASE=:memory:` — mirroring `phpunit.xml`'s `<php><env>` entries key for key. Config resolution verified by execution, not by reading: `php artisan config:show database.default --env=testing` reports `sqlite` and `database.connections.sqlite.database` reports `:memory:`, while `php artisan config:show database.default` with no flag reports `mysql`. The dev database is still MySQL `petconnect` — see the "MySQL in development and SQLite under test" note above in this file.

What was true before the fix, and why the flag felt safe when it was not: without `.env.testing`, Laravel falls back to plain `.env` with only `APP_ENV` overridden. An orchestrator issued `migrate:fresh --env=testing` under exactly those conditions this phase; the agent resolved what the override actually pointed at before running anything destructive, declined it, and migrated into a throwaway SQLite file using explicit shell env overrides instead.

Safe pattern that still stands: resolve what an env flag actually points at before running anything that writes, and give any destructive command an explicit `DB_CONNECTION=`/`DB_DATABASE=` override when you are not certain. Keep `.env.testing` and `phpunit.xml` in agreement — change one, change the other in the same commit, or the suite and `--env=testing` will quietly disagree.

General lesson: verifying a destructive command's real effect and declining an instruction that would destroy data is correct behaviour even when the instruction comes from the orchestrator.

## nova_notifications has the same index gap as notifications — deferred on purpose
`nova_notifications` carries the same bare 2-column `morphs('notifiable')` index and the same query shape that motivated adding `(notifiable_type, notifiable_id, created_at)` and `(notifiable_type, notifiable_id, read_at)` to the framework's `notifications` table this phase. It was **not** fixed, deliberately.

Query shapes (verified in `Laravel\Nova\Http\Requests\NotificationRequest`): the panel list is `where notifiable_type = ? and notifiable_id = ? order by created_at desc limit 100`; the badge is `where read_at is null and notifiable_type = ? and notifiable_id = ? count(*)`. Note the Nova model uses `SoftDeletes` (the framework `notifications` table does not), so `deleted_at is null` rides along on every one of those — a composite here would want `deleted_at` in it too.

Why deferred: it is a **vendor** migration (`vendor/laravel/nova/database/migrations/2021_08_25_193039_create_nova_notifications_table.php`), not in `database/migrations/`, so it cannot be edited in place like the others. Fixing it means publishing the vendor migration or carrying a standalone `ALTER` migration — both add maintenance surface that has to survive Nova upgrades. And it backs the **admin** notification panel, not the member bell, so row growth and read volume are a fraction of `notifications`.

Revisit when: admin notification volume becomes non-trivial, or the admin panel goes slow. Then `EXPLAIN` the two queries above on MySQL 8 at the real row count before adding anything.

## migrate:fresh --env=testing is now safe but inert — do not "improve" :memory: into a file
Since `.env.testing` landed (7125ba2), `php artisan migrate:fresh --env=testing` resolves to `DB_CONNECTION=sqlite` / `DB_DATABASE=:memory:`. Verified: `config:show database.default --env=testing` is `sqlite`, `:memory:`; without the flag it is `mysql`. So the command destroys nothing — and accomplishes nothing, because the database dies with the process. It is safe, and it is inert. Do not report it as a from-scratch migration check.

The trap: nobody should "improve" `:memory:` into a file path so the result survives. That silently breaks the key-for-key agreement with `phpunit.xml` that `.env.testing` exists to uphold, and makes `migrate:fresh --env=testing` genuinely destructive again the moment someone points it somewhere real.

If you actually need to prove the migrations run from scratch, run them against an explicitly-named scratch database via explicit `DB_CONNECTION=`/`DB_DATABASE=` shell overrides. Never against the dev `petconnect` MySQL database.
