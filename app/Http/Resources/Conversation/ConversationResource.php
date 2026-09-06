<?php

namespace App\Http\Resources\Conversation;

use App\Http\Resources\Message\MessageResource;
use App\Http\Resources\User\UserSummaryResource;
use App\Models\Conversation;
use App\Models\Message;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * A conversation, in the inbox and on the thread page.
 *
 * The single conversation payload: `conversations.index` emits a page of these
 * with `last_message` and `unread` filled in, and `conversations.show` emits
 * one without them, because the page below it is the messages themselves. The
 * legacy pair (ConversationResource plus ConversationSummaryResource) existed
 * only so a message could embed a conversation that would not embed messages
 * back; nothing here embeds a message that embeds a conversation, so one class
 * covers both.
 *
 * ## No write counterpart
 *
 * There is no conversation form to round-trip. `conversations.store` posts
 * `recipient_id` and `initial_message`, and neither is a key of this resource —
 * deliberately, and per .ai/rules/resources.md: `peer` is a user object and
 * `recipient_id` is an id, so giving them the same name would tell a client to
 * post back a shape the validator rejects. The read side names people
 * (`peer`, `participants`); the write side names ids.
 *
 * ## `peer` and `unread` are computed, and both need relations loaded
 *
 * `peer` is the other side of a direct conversation, resolved against the
 * viewer rather than stored — the participants live in `conversation_user` and
 * neither row is "the peer" except relative to whoever is reading.
 *
 * `unread` compares the last message against the viewer's
 * `conversation_user.last_read_at`. There is no `messages.read_at` column: the
 * cursor is the entire read model, and App\Models\ConversationUser casts it to
 * a Carbon so the comparison happens in PHP over rows that are already loaded.
 *
 * Both are guarded on `relationLoaded()` and fall back to a neutral value
 * rather than reaching through an unloaded relation. A loader that forgets
 * `users` or `lastMessage` therefore ships a null peer instead of a query per
 * conversation — and, on a result set of one row, instead of a lazy load that
 * Model::preventLazyLoading() would not even catch (see .ai/rules/app.md).
 * App\Actions\Messaging\BuildInbox loads all of it, `users.media` included,
 * which is the avatar N+1 the legacy inbox had.
 *
 * ## `can_send` is the composer's own gate, and it costs no query
 *
 * `messages.store` authorizes through MessagePolicy::create, which asks three
 * questions — is the sender verified, are they in this thread, and is the other
 * side still accepting from them. Without this key a client had no way to know
 * any of that before the user had typed: the composer rendered unconditionally
 * and the refusal arrived as a 403 on submit, after the message was written.
 * Deactivate the peer and the honest answer is "this thread is read-only", not
 * an error on send.
 *
 * The policy is asked here rather than reimplemented, per .ai/rules/policies.md
 * — a flag the frontend derives itself is a second copy of the rule. Asking it
 * is free **because the participants are loaded**: Conversation::hasParticipant()
 * answers from the loaded `users` collection, and acceptsMessagesFrom() walks
 * the same collection asking User::isActive(), which is a column already on
 * each row. Neither touches the database once `users` is loaded, which matters
 * because this resource is emitted once per row on the inbox, not only once on
 * the thread page.
 *
 * When `users` is *not* loaded the key is `false` rather than a query — the
 * same neutral fallback `peer` and `unread` take, for the same reason. A loader
 * that forgets the relation ships a read-only-looking thread, which is visible
 * and wrong in the safe direction, instead of one query per row.
 *
 * @mixin Conversation
 */
class ConversationResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $viewer = $this->viewer($request);
        $hasParticipants = $this->relationLoaded('users');

        return [
            'id' => $this->id,
            'type' => $this->type,
            'last_message_at' => $this->last_message_at,

            'participants' => UserSummaryResource::collection($this->whenLoaded('users')),
            'peer' => $this->when(
                $hasParticipants && $viewer !== null,
                fn (): ?UserSummaryResource => $this->peerResource($viewer),
                null,
            ),

            'last_message' => $this->whenLoaded(
                'lastMessage',
                fn (): MessageResource => MessageResource::make($this->lastMessage),
                null,
            ),
            'unread' => $this->when(
                $hasParticipants && $this->relationLoaded('lastMessage') && $viewer !== null,
                fn (): bool => $this->isUnreadFor($viewer),
                false,
            ),

            'can_send' => $hasParticipants
                && $viewer !== null
                && $viewer->can('create', [Message::class, $this->resource]),

            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }

    /**
     * The signed-in member reading this row, or null.
     *
     * `$request->user()` can resolve on either guard — `App\Models\Admin` signs
     * in on `admins` — and `otherParticipant()`, `isUnreadFor()` and
     * MessagePolicy::create are all typed against `App\Models\User`. An admin is
     * not a participant in anybody's conversation, so narrowing to null here is
     * the honest answer as well as the typed one; without it the union reaches a
     * `User` parameter and the failure mode is a TypeError while rendering.
     */
    protected function viewer(Request $request): ?User
    {
        $viewer = $request->user();

        return $viewer instanceof User ? $viewer : null;
    }

    /**
     * The other side of the conversation, or null when there is nobody else in
     * it.
     *
     * A conversation with one participant should not exist —
     * StartDirectConversation\EnsureDistinctParticipants and
     * AttachParticipants together guarantee two — but a resource must not
     * serialise a JsonResource wrapping null, which throws while rendering
     * rather than while writing. Null here is the payload saying so.
     */
    protected function peerResource(User $viewer): ?UserSummaryResource
    {
        $peer = $this->otherParticipant($viewer);

        return $peer === null ? null : UserSummaryResource::make($peer);
    }
}
