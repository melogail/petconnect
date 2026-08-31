<?php

namespace App\Http\Resources\Message;

use App\Http\Resources\User\UserSummaryResource;
use App\Models\Message;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * A message, wherever it is rendered.
 *
 * The single message payload in the application: the thread page, the paging
 * endpoint behind it, and the `last_message` preview inside
 * Conversation\ConversationResource all emit this class, so a client needs one
 * reader for all three. The legacy app had this shape plus a second
 * ConversationSummaryResource nested inside it, which meant a message knew how
 * to describe its own conversation and a conversation knew how to describe its
 * messages — a cycle that only terminated because each side happened to check
 * `relationLoaded()`.
 *
 * ## The message form contract
 *
 * Two keys are write shapes as well as read shapes, emitted under exactly the
 * name the Form Requests accept, so round-tripping a message into an edit box
 * is a straight assignment:
 *
 * | emitted   | StoreMessageRequest        | UpdateMessageRequest |
 * |-----------|----------------------------|----------------------|
 * | `content` | required, string, max      | required, string, max |
 * | `type`    | sometimes, Rule::enum      | — (not editable)      |
 *
 * Everything else is a read shape with no write counterpart: `id`,
 * `conversation_id`, `sender_id`, `sender`, `status`, `is_mine`, `is_edited`,
 * `edited_at`, `is_pinned`, `pinned_at`, `pinned_by`, `can_edit`, `can_delete`,
 * `can_pin`, `created_at`, `updated_at`.
 *
 * Neither write key carries `present`. The pet form needs it because a PUT
 * there replaces the whole listing and an omitted key is written as null; a
 * message has one editable column behind a `required` rule, and an omitted
 * `type` means "text", which is a correct answer rather than a silent wipe. See
 * .ai/rules/requests.md for when `present` is the guard and when it is noise.
 *
 * ## `pinned_by` is an id, not an object
 *
 * Every other user on this payload is a UserSummaryResource, and `pinned_by`
 * deliberately is not. Rendering it as one would oblige every loader to eager
 * load `pinnedBy.media` for an avatar that appears on the handful of pinned
 * messages in a thread, and forgetting it is one query per message. The client
 * already holds the participants from the conversation payload and can match
 * the id against them; a direct conversation has two.
 *
 * ## The three `can_*` flags cost no queries, by three different mechanisms
 *
 * `can_edit` and `can_delete` ask MessagePolicy, which decides from attributes
 * already on the model — the sender id, `created_at` and the edit window — and
 * touches the database in neither method. That is a property of the policy this
 * resource depends on: a participation query in `update` or `delete` would turn
 * a fifty-message page into a hundred extra queries.
 *
 * `can_pin` is the harder one, and it used not to be here at all. Pinning is
 * open to any participant, so MessagePolicy::pin asks
 * `$message->conversation->hasParticipant($viewer)` — a relation walk and,
 * until now, a query. The frontend filled the gap by assuming the policy would
 * agree with a check it made itself, which is a policy reimplemented in Vue
 * (.ai/rules/policies.md). Two changes make asking the real policy free:
 *
 * 1. Both loaders **chaperone** the conversation onto every message they
 *    return, so `$message->conversation` is the very instance the page already
 *    holds rather than a lazy load. Actions\Messaging\PaginateConversationMessages
 *    and Actions\Messaging\BuildInbox both do it; chaperone is pure PHP and
 *    issues nothing.
 * 2. Conversation::hasParticipant() answers from the loaded `users` collection
 *    when there is one, and both loaders load it.
 *
 * When `conversation` is *not* loaded the key is `false` rather than a lazy
 * load — the neutral fallback the counts and flags elsewhere in this
 * application take, and the only one available here: `whenLoaded()` would drop
 * the key silently and let a client read `undefined` as "allowed", while
 * reaching through the relation would be a query per rendered row on the
 * payload most likely to hold fifty of them.
 *
 * ## `is_edited` reads `edited_at`, never the timestamps
 *
 * It used to be `updated_at?->gt($created_at)`, and that was wrong: `updated_at`
 * is the row's last-write stamp, so pinning a message — which touches nothing a
 * reader can see except a flag — shipped `is_edited: true` for a message nobody
 * had rewritten. A restore, and any delivery-state column a later phase adds,
 * would have forged the same trace. `messages.edited_at` is written by
 * Pipelines\Messages\Revise\PersistContent and by nothing else, so the pair
 * emitted here (`is_edited` and the timestamp behind it, mirroring
 * `is_pinned`/`pinned_at`) means exactly "the sender revised the words".
 *
 * `is_edited` is an appended attribute on Message rather than a comparison
 * written out here, so a Nova panel or a console dump answers it the same way
 * this payload does.
 *
 * @mixin Message
 */
class MessageResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $viewer = $request->user();

        return [
            'id' => $this->id,
            'conversation_id' => $this->conversation_id,
            'content' => $this->content,
            'type' => $this->type,
            'status' => $this->status,

            'sender_id' => $this->sender_id,
            'sender' => UserSummaryResource::make($this->whenLoaded('sender')),
            'is_mine' => $viewer?->getKey() === $this->sender_id,

            'is_edited' => $this->is_edited,
            'edited_at' => $this->edited_at,
            'is_pinned' => $this->is_pinned,
            'pinned_at' => $this->pinned_at,
            'pinned_by' => $this->pinned_by,

            'can_edit' => (bool) $viewer?->can('update', $this->resource),
            'can_delete' => (bool) $viewer?->can('delete', $this->resource),
            'can_pin' => $this->relationLoaded('conversation')
                && $viewer !== null
                && $viewer->can('pin', $this->resource),

            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
