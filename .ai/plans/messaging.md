# Messaging

## Purpose
One-to-one conversations between a buyer/adopter and a listing owner, delivered in real time over Reverb.

## Data
`conversations` (type `direct`, last_message_at, soft deletes), `conversation_user` pivot (last_read_at), `messages` (sender_id, content, type `text`, status, pinned_by, pinned_at, edited_at, soft deletes).

## Backend
- Actions (`app/Actions/Messaging/`): StartConversation, SendMessage, UpdateMessage, DeleteMessage, TogglePinMessage, MarkConversationAsRead, BuildInbox, ListConversationPreviews, PaginateConversationMessages, LoadConversationParticipants, CountUnreadConversations.
- Pipelines (`app/Pipelines/Messages/`): StartDirectConversation (EnsureDistinctParticipants → EnsureRecipientAccepts → FindExistingDirectConversation → CreateConversationRecord → AttachParticipants → SendInitialMessage → MarkReadForInitiator), Send (EnsureParticipant → EnsureRecipientAccepts → CleanContent → PersistMessage → NotifyRecipient), Revise (CleanContent → PersistContent).
- `ConversationPolicy`, `MessagePolicy`, `MessageValidationRules`, requests under `Conversation/` and `Message/`, resources under `Conversation/` and `Message/`.
- `MessageObserver` keeps `conversations.last_message_at` current on create/delete/restore/force delete.
- `NewMessageNotification` (database + broadcast) on the private channel `App.Models.User.{id}` (`routes/channels.php`). `User::acceptsMessagesFrom()` blocks inactive accounts.

## Routes
Web: `GET conversations`, `GET conversations/previews`, `POST conversations`, `GET conversations/{conversation}`, `GET|POST conversations/{conversation}/messages`, `POST conversations/{conversation}/read`, `PUT|DELETE messages/{message}`, `POST messages/{message}/pin`. API mirrors them under `api/v1`. All need `auth` + `verified`.

## Frontend
`pages/messaging/{Index,Show}.vue`, inbox components under `components/shell/` (unread badge), Echo via `@laravel/echo-vue` + `laravel-echo` + `pusher-js` against Reverb. Rules for this area: `.ai/rules/messaging.md`, `.ai/rules/messages.md`.

## Guards
Throttles `conversations` (5/min), `messages` (30/min), `content-edits`, `inbox-actions` (60/min). Max length from `bounds.messages`.

## Admin
None (conversations are private; no Nova resource).

## Tests
`tests/Feature/Actions/Messaging/`, `tests/Feature/Http/Resources/{Conversation,Message}/`, `tests/Feature/Policies/`.

## Open work
- Only text messages; no images or listing cards inside the chat.
- No block/mute between users beyond account deactivation.
- Horizon must be running in production for queued broadcasts and notifications.
