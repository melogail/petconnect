---
paths:
  - 'database/seeders/**'
---

# Seeders

## Every seeder wraps its run() in DB::transaction(), and model events stay on
Each seeder's run() body sits inside a single DB::transaction(). On SQLite every firstOrCreate/updateOrCreate and every notify() would otherwise be its own implicit transaction and its own fsync — batching per seeder took `migrate:fresh --seed` from ~29s to ~4s (LikeSeeder alone 17.5s -> 1.8s). Wrap per seeder, not the whole chain, so each step stays all-or-nothing.

Do NOT mute model events to speed seeding up: UserObserver assigns the unique media_directory_name, MessageObserver maintains conversations.last_message_at, and LikeObserver writes the notifications the dashboard reads. ModelLikedNotification is not queued, so it is safe inside the transaction.

Every seeder must stay idempotent — top a target count up (`max(0, TARGET - count())`) or filter with doesntHave(), never append. A second `php artisan db:seed` must leave identical row counts.
