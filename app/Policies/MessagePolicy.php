<?php

namespace App\Policies;

use App\Models\Conversation;
use App\Models\Message;
use App\Models\User;

/**
 * Authorization for messages.
 *
 * Called with $this->authorize() in MessageController, per
 * .ai/rules/controllers.md. The legacy MessageController called this policy in
 * exactly one of its three actions (`destroy`); `store` and `update` were
 * authorized from inside their Form Requests, where the call site shows
 * nothing. All three ask here now.
 *
 * ## Two of these methods must not touch the database
 *
 * `update` and `delete` are asked once per message by
 * App\Http\Resources\Message\MessageResource, to emit `can_edit` and
 * `can_delete` on a page that renders thirty of them. Both therefore decide
 * from attributes already on the model — the sender id, `created_at`, and a
 * window read from config — and neither loads a relation. A participation
 * check in either would be thirty extra queries a page, which is why the
 * sender's own membership is taken as settled instead: a `messages` row can
 * only be written by Pipelines\Messages\Send, which refuses a non-participant,
 * so `sender_id === $user->id` already implies participation.
 *
 * `create` and `pin` also went on payloads in Phase 4a, as
 * ConversationResource::can_send and MessageResource::can_pin, and they are the
 * interesting case: both ask Conversation::hasParticipant() — and `create` also
 * Conversation::acceptsMessagesFrom() — which reads like exactly the query this
 * file forbids per row.
 *
 * It is not, and the reason is worth stating so nobody 'fixes' it back.
 * hasParticipant() answers from the loaded `users` collection when the
 * relation is loaded, and acceptsMessagesFrom() walks that same collection
 * asking User::isActive(), a column already on each row. Every payload that
 * asks these two loads `users` first — BuildInbox and LoadConversationParticipants
 * for the conversation, and PaginateConversationMessages / BuildInbox chaperone
 * the conversation back onto each message so `pin` reaches a loaded instance
 * rather than a lazy one. Asked against a bare route-bound model, as the
 * controllers ask them, they still issue their one query, which is correct
 * there: that is once per request, not once per row.
 *
 * The resources hold up the other end of the bargain. Both emit `false` rather
 * than asking at all when the relation they depend on is missing, so a loader
 * that forgets one degrades a flag instead of issuing a query per row.
 *
 * ## The edit window
 *
 * `update` closes `petconnect.messaging.edit_window_minutes` after the message
 * was sent. The legacy policy had no window: a message could be rewritten
 * indefinitely, including long after the other side had read it and acted on
 * it, and the only trace was `updated_at` moving. Correspondence is not a wiki
 * page — a bounded window covers the typo the sender notices immediately, which
 * is what editing is for, and stops the message that somebody agreed to from
 * silently becoming a different message. Set the window to 0 to make messages
 * immutable once sent; a full edit history, which is the other honest answer,
 * is a table this vertical does not have.
 *
 * Deletion is *not* windowed. Withdrawing something you wrote is a different
 * act from rewriting it into something you did not, it leaves the row behind
 * (messages soft delete), and the recipient sees it disappear rather than
 * change meaning.
 *
 * There is no `view` or `viewAny` here. A message is never addressed on its own
 * for reading — it is read through its conversation, which
 * ConversationPolicy::view already gates — and the legacy `viewAny` returned
 * false while `view` delegated to the conversation, which is two methods
 * saying what one already said.
 */
class MessagePolicy
{
    /**
     * Sending a message into a conversation.
     *
     * The conversation is passed as a second argument rather than inferred,
     * because there is no message yet to read it off:
     * `$this->authorize('create', [Message::class, $conversation])`.
     *
     * Three questions, not one: is the sender verified, do they belong in this
     * thread, and is the other side still accepting from them. The third used
     * to be asked only when a conversation was *opened*, which meant a
     * deactivated account kept receiving messages in every thread it already
     * had. It is asked here so that the answer over HTTP is a 403 rather than
     * the 500 the pipeline's invariant would give — see
     * Pipelines\Messages\Send\EnsureRecipientAccepts, which asks the same
     * question of Conversation::acceptsMessagesFrom() for the callers that pass
     * no policy.
     *
     * Asked once per request from MessageController::store against a
     * route-bound conversation, where its one query is correct, and once per
     * row from Http\Resources\Conversation\ConversationResource as `can_send`,
     * where it is free because that payload has the participants loaded. Both
     * halves come from Conversation::hasParticipant() and
     * Conversation::acceptsMessagesFrom() answering off a loaded `users`
     * collection when there is one — see the class docblock. On the send path
     * the loadMissing inside acceptsMessagesFrom() also costs nothing, because
     * Send\EnsureParticipant needs the same relation a moment later.
     */
    public function create(User $user, Conversation $conversation): bool
    {
        return $user->isVerified()
            && $conversation->hasParticipant($user)
            && $conversation->acceptsMessagesFrom($user);
    }

    /**
     * Editing a message: the sender, inside the window.
     *
     * Query-free by design — see the class docblock.
     */
    public function update(User $user, Message $message): bool
    {
        return $user->isVerified()
            && $user->getKey() === $message->sender_id
            && $this->withinEditWindow($message);
    }

    /**
     * Withdrawing a message: the sender, at any time.
     *
     * Query-free by design — see the class docblock.
     */
    public function delete(User $user, Message $message): bool
    {
        return $user->isVerified() && $user->getKey() === $message->sender_id;
    }

    /**
     * Pinning a message: any participant, either side's message.
     *
     * Not restricted to the sender on purpose. Pinning is a bookmark, and the
     * thing worth bookmarking in a direct conversation is usually what the
     * other person said — an address, a price, a time. Who pinned it is
     * recorded in `pinned_by` by App\Actions\Messaging\TogglePinMessage.
     *
     * Http\Resources\Message\MessageResource asks this per rendered message,
     * as `can_pin`, so `$message->conversation` must already be on the model:
     * both message loaders chaperone it there, and hasParticipant() then
     * answers from the conversation's loaded `users`. Before that flag existed
     * the frontend decided pinning itself from the same two facts, which is a
     * policy written twice. Do not add a check here that cannot be answered
     * from a loaded relation.
     */
    public function pin(User $user, Message $message): bool
    {
        return $user->isVerified() && $message->conversation->hasParticipant($user);
    }

    /**
     * Whether the message is still young enough to be edited.
     *
     * A window of 0 minutes makes every message immutable the moment it is
     * sent, which is a supported configuration rather than an edge case.
     */
    protected function withinEditWindow(Message $message): bool
    {
        $windowMinutes = (int) config('petconnect.messaging.edit_window_minutes', 15);

        if ($windowMinutes <= 0) {
            return false;
        }

        return $message->created_at !== null
            && now()->lessThan($message->created_at->addMinutes($windowMinutes));
    }
}
