<?php

namespace App\Http\Resources\Conversation;

use App\Http\Resources\User\UserSummaryResource;
use App\Models\Conversation;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

/**
 * One row of the header's messages menu: who it is with, what was said last,
 * when, and whether it is unread.
 *
 * ## Why a second conversation payload exists, when a third comment payload did not
 *
 * .ai/rules/resources.md records that `Pet\PetCommentResource` was deleted for
 * being a per-domain copy, and that is the rule this class has to answer to.
 * The competing rule is the one about weight: a shared prop, or anything
 * fetched once per document load by every signed-in visitor, carries the least
 * it can.
 *
 * The second rule wins here because the two cases are not the same shape of
 * mistake. `PetCommentResource` emitted the **same keys** as `CommentResource`
 * for the same rows on the same page — two names for one payload, where a
 * rename in one silently shipped a different comment object per screen. This is
 * a different projection with a different fetch profile: six keys where
 * ConversationResource has ten, no `participants`, no `can_send`, no embedded
 * MessageResource, and a **server-truncated** body instead of the whole message.
 *
 * Measured on the development MySQL database, user 1, 5 conversations, no
 * avatars attached: ConversationResource in a paginator serialised to **5,822
 * bytes** (1,055 per row, of which `participants` 218, `peer` 111 and the
 * embedded `last_message` 488; 227 more for the `meta.links` of a menu that
 * does not page). The same five rows through this class are **1,531 bytes**,
 * 299 per row — **3.8x smaller**, and `peer` is 111 of those 299, which is why
 * it is the only nested object left. The gap grows with the message, not with
 * the row count:
 * `MessageResource` emits `content` in full against a
 * `petconnect.messaging.max_length` of 5,000, so five long messages are ~25 KB
 * on every page load, while the snippet is capped at
 * `petconnect.messaging.preview_snippet_length`.
 *
 * There was never an exposure problem to fix — every key ConversationResource
 * emits already reaches this viewer on the inbox page. This is about bytes on a
 * request nobody asked for, and about a menu not being able to grow into a
 * second inbox by accident.
 *
 * **Do not "consolidate" this back into ConversationResource.** If the two ever
 * do converge, the thing to check is whether the dropdown started rendering
 * participants and send permissions — not whether the classes look similar.
 *
 * ## The keys, and why each one is here
 *
 * - `peer` is the whole reason the menu needs the participants loaded, and it
 *   is a full UserSummaryResource rather than a narrower one on purpose: that
 *   class's docblock records that three byte-identical user summaries once
 *   existed and were collapsed into it, so a seventh key would be cheaper than
 *   a fourth user shape. `location` is the only key the dropdown does not
 *   render, and it is 30 bytes on the row measured above ("Suez, Suez, Egypt")
 *   against a payload that just shed 4,291.
 * - `last_message_at` is `conversations.last_message_at`, which
 *   App\Observers\MessageObserver keeps equal to the last undeleted message's
 *   `created_at` (the invariant Actions\Messaging\CountUnreadConversations
 *   documents in full). It is the same instant the client would read off the
 *   message, from a column already on the row, and it is the same key with the
 *   same meaning ConversationResource emits — the two payloads cannot disagree
 *   about it.
 * - `last_message_sender_id` is an id rather than a sender object, for the
 *   reason MessageResource gives for `pinned_by`: rendering a user costs the
 *   loader an avatar eager load. The client compares it to `auth.user.id` to
 *   prefix "You: " and otherwise has the peer already.
 * - `unread` is `Conversation::isUnreadFor()`, the same predicate the badge's
 *   aggregate agrees with clause for clause.
 *
 * ## Every key falls back rather than reaching through an unloaded relation
 *
 * `peer`, the snippet and the sender id are null and `unread` is false when the
 * relation behind them is missing — not `whenLoaded()`, which drops the key and
 * lets a client read `undefined` as a value, and not a lazy load, which is a
 * query per row on the most frequently fetched endpoint in the application.
 * `Model::preventLazyLoading()` would not catch it either on a result set of
 * one row (.ai/rules/app.md). Actions\Messaging\ListConversationPreviews loads
 * both relations; a loader that forgets one ships a visibly empty menu.
 *
 * @mixin Conversation
 */
class ConversationPreviewResource extends JsonResource
{
    /**
     * @return array{
     *     id: int,
     *     peer: UserSummaryResource|null,
     *     last_message_at: Carbon|null,
     *     last_message_snippet: string|null,
     *     last_message_sender_id: int|null,
     *     unread: bool
     * }
     */
    public function toArray(Request $request): array
    {
        $viewer = $this->viewer($request);
        $hasParticipants = $this->relationLoaded('users');
        $lastMessage = $this->relationLoaded('lastMessage') ? $this->lastMessage : null;

        return [
            'id' => $this->id,

            'peer' => $hasParticipants && $viewer !== null
                ? $this->peerResource($viewer)
                : null,

            'last_message_at' => $this->last_message_at,
            'last_message_snippet' => $lastMessage === null
                ? null
                : Str::limit($lastMessage->content, $this->snippetLength()),
            'last_message_sender_id' => $lastMessage?->sender_id,

            'unread' => $hasParticipants
                && $lastMessage !== null
                && $viewer !== null
                && $this->isUnreadFor($viewer),
        ];
    }

    /**
     * The signed-in member reading this row, or null.
     *
     * `$request->user()` can resolve on either guard — `App\Models\Admin` signs
     * in on `admins` — and every predicate below (`otherParticipant()`,
     * `isUnreadFor()`) is typed against `App\Models\User`. An admin is not a
     * participant in anybody's conversation, so narrowing to null here is the
     * honest answer as well as the typed one; without it the union reaches a
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
     * Null for the same reason Conversation\ConversationResource gives: a
     * one-participant conversation should not exist, but a resource must not
     * serialise a JsonResource wrapping null, which throws while rendering
     * rather than while writing.
     */
    protected function peerResource(User $viewer): ?UserSummaryResource
    {
        $peer = $this->otherParticipant($viewer);

        return $peer === null ? null : UserSummaryResource::make($peer);
    }

    /**
     * How many characters of the last message the menu is given.
     *
     * A snippet is not a message: the row is one line in a dropdown, so the
     * bound is a display decision and lives in `config/petconnect.php` beside
     * `preview_per_page` rather than being derived from
     * `petconnect.messaging.max_length`, which is a validation ceiling for the
     * whole correspondence.
     */
    protected function snippetLength(): int
    {
        return (int) config('petconnect.messaging.preview_snippet_length', 120);
    }
}
