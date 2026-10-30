# Messaging & notifications

Direct, one-to-one conversations between members, usually started from a listing or a profile.

## Model

```
Conversation (type=direct, last_message_at, soft deletes)
  ├─ conversation_user  (pivot: participant, read cursor; unique per pair)
  └─ Message (sender, content, type=text, status, pinned_by/at, edited_at)
```

`ConversationPolicy` limits every read and write to participants. `MessagePolicy` allows the sender to edit and delete their own messages; either participant can pin.

## Routes

| Action | Web | Limiter |
| --- | --- | --- |
| Inbox | `GET conversations` | — |
| Inbox previews (polling) | `GET conversations/previews` | — |
| Open | `GET conversations/{conversation}` | — |
| Start | `POST conversations` | `conversations` |
| Messages page | `GET conversations/{conversation}/messages` | — |
| Send | `POST conversations/{conversation}/messages` | `messages` |
| Mark read | `POST conversations/{conversation}/read` | `inbox-actions` |
| Edit / delete | `PUT|DELETE messages/{message}` | `content-edits` |
| Pin | `POST messages/{message}/pin` | `content-edits` |

API v1 mirrors these under `api/v1/conversations` and `api/v1/messages`.

## Server flow

Sending runs the Messages pipeline (`CleanContent`), stores the message, bumps `last_message_at`, and dispatches `NewMessageNotification` to the other participant. The notification is queued after the transaction commits, then stored as a database notification and broadcast on the recipient's private channel.

Starting a conversation with someone you already have one with returns the existing one; the pivot's unique index makes that race-safe.

## Real time

- Channel: `App.Models.User.{id}` (private, authorised in `routes/channels.php`).
- Server: Reverb when `BROADCAST_CONNECTION=reverb`; `log` locally by default.
- Client: `@laravel/echo-vue` composables in the messaging pages subscribe for new messages and unread counts; the header badge (`unreadBadgeLabel`) updates without a reload.

## Inbox housekeeping

`conversations.read`, `notifications.read-all` and `notifications.destroy-all` are client-fired rather than user-fired, which is why they sit on the looser `inbox-actions` limiter.

## Known gaps

- Only `direct` conversations and `text` messages exist; the enums leave room for more.
- Message `status` currently only moves to `sent`; delivery/read states per message are not modelled beyond the pivot read cursor.
