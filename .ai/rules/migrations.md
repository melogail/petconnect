---
paths:
  - 'database/migrations/**'
---

# Migrations

## RESTRICT + soft deletes: deleting a category fails even when pets look deleted
`pets.category_id` is `restrictOnDelete` and `pets` uses `softDeletes()`. A soft-deleted pet still holds its `category_id` row, so deleting a Category (in Nova or anywhere) throws a raw DB foreign-key constraint error even when every visible pet has been "deleted". Phase 3 must add an explicit guard (block the delete, or force-delete/reassign trashed pets first) and surface a friendly message instead of the driver exception. The same trap applies to any future RESTRICT FK pointing at a soft-deleting table.

## Index foreign-key columns explicitly: SQLite creates no FK index
This project runs SQLite (`DB_CONNECTION=sqlite` in `.env`, `.env.example`, `phpunit.xml`) and SQLite does NOT auto-create an index for a foreign key, so `foreignId()->constrained()` leaves the column unindexed. Declare `$table->index('<fk_column>')` explicitly for every FK you actually filter or join on. A FK that is already the leading column of a composite or unique index (e.g. `conversation_user.conversation_id`) needs no separate index. Do not maintain a list of which columns are covered here — read the migration.

MySQL/InnoDB does NOT make these explicit indexes redundant. InnoDB creates its own FK index only when no suitable index already exists; because these `index()` calls sit in the same Blueprint, InnoDB adopts them instead of adding its own. They are not duplicates. Keep them — dropping them on a move to MySQL just makes InnoDB silently recreate them under generated names.
