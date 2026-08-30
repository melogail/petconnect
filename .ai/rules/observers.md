---
paths:
  - 'app/Observers/**'
---

# Observers

## Three observers hold invariants seeders and factories depend on — never mute model events
UserObserver::creating assigns the opaque `media_directory_name` — `random_int(10 ** 15, 10 ** 18)`, so 16 to 19 digits (the bound is inclusive) — so uploads are never addressable by user id; factories and seeders deliberately leave the column unset. Uniqueness is the DB unique index's guarantee, not the observer's: the observer draws at random and does not check for a collision.
MessageObserver maintains `conversations.last_message_at`: `created` pushes the new message's created_at, and `deleted` (soft only — it returns early while force deleting), `restored` and `forceDeleted` recompute the cursor from the remaining messages, so it goes null when the last one is gone.
LikeObserver early-returns unless the likeable implements App\Contracts\Likeable, then notifies `likeNotificationRecipients()` minus the liker, so self-likes are silent. ModelLikedNotification is not queued.
Consequence: do not mute model events to speed seeding up (see .ai/rules/seeders.md) and do not use insert()/upsert() where these invariants matter — they bypass the observers.
