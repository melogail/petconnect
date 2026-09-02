---
paths:
  - 'database/migrations/**'
---

# Migrations

## RESTRICT + soft deletes: deleting a category fails even when pets look deleted
`pets.category_id` is `restrictOnDelete` and `pets` uses `softDeletes()`. A soft-deleted pet still holds its `category_id` row, so deleting a Category (in Nova or anywhere) throws a raw DB foreign-key constraint error even when every visible pet has been "deleted". Phase 3 must add an explicit guard (block the delete, or force-delete/reassign trashed pets first) and surface a friendly message instead of the driver exception. The same trap applies to any future RESTRICT FK pointing at a soft-deleting table.

## The database is MySQL in development and SQLite under test — check before you reason about a plan
**Corrected 2026-08-31.** This file used to open "This project runs SQLite (`DB_CONNECTION=sqlite` in `.env`, `.env.example`, `phpunit.xml`)". That is wrong and it was the stated justification for every index decision in this port. Measured: `.env` is `DB_CONNECTION=mysql` and `php artisan db:show` reports **MySQL 8.0.46**, database `petconnect`. Only `.env.example` and `phpunit.xml` are SQLite — so the split is **MySQL in dev (and therefore whatever prod becomes), SQLite in memory under test**.

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
