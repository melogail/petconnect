import type { UserSummary } from './users';

export type MessageType = 'text' | 'image' | 'file';
export type MessageStatus = 'sent' | 'delivered' | 'read';
export type ConversationType = 'private' | 'group';

/**
 * One message — `App\Http\Resources\Message\MessageResource`.
 *
 * `is_edited` is backed by the `edited_at` column, not by `updated_at`: pinning
 * a message writes the row without changing anything a reader can see, so a
 * flag derived from `updated_at` would publish "edited" for it.
 *
 * `can_edit` closes on its own — `MessagePolicy::update` only allows the sender
 * inside `petconnect.messaging.edit_window_minutes` (15 by default) — so it is
 * accurate at render time and stale afterwards.
 */
export type Message = {
    id: number;
    conversation_id: number;
    content: string;
    type: MessageType;
    status: MessageStatus;
    sender_id: number;
    /** Only present when the backend eager loaded the sender. */
    sender?: UserSummary;
    is_mine: boolean;
    is_edited: boolean;
    edited_at: string | null;
    is_pinned: boolean;
    pinned_at: string | null;
    pinned_by: number | null;
    can_edit: boolean;
    can_delete: boolean;
    /**
     * `MessagePolicy::pin`, answered per row.
     *
     * It is `false` whenever the loader did not `chaperone()` the conversation
     * onto the message, so it is never `undefined` and must not be re-derived
     * in Vue from the participant list — that is exactly the duplication
     * `.ai/rules/resources.md` records.
     */
    can_pin: boolean;
    created_at: string;
    updated_at: string;
};

/**
 * One conversation — `App\Http\Resources\Conversation\ConversationResource`.
 *
 * `peer`, `last_message` and `unread` all depend on eager loads: the inbox
 * carries every one of them, the thread page carries participants only, so
 * `unread` is `false` there whatever the read cursor says. Only the inbox may
 * be trusted to answer "is this thread unread".
 */
export type Conversation = {
    id: number;
    type: ConversationType;
    last_message_at: string | null;
    /** Only present when the backend eager loaded the participants. */
    participants?: UserSummary[];
    /** The other participant, from the viewer's point of view. */
    peer?: UserSummary | null;
    last_message?: Message | null;
    unread: boolean;
    /**
     * `MessagePolicy::create` against this thread, answered per row.
     *
     * The composer gates on it, so "this person is not accepting messages"
     * is visible before anything is typed rather than as a 403 afterwards.
     * `false` for a guest and whenever the participants were not loaded.
     */
    can_send: boolean;
    created_at: string;
    updated_at: string;
};

/**
 * One row of the header's messages menu —
 * `App\Http\Resources\Conversation\ConversationPreviewResource`.
 *
 * A narrower projection of the same model `Conversation` describes, not a
 * second name for it: six keys where `ConversationResource` has ten, no
 * `participants`, no `can_send`, and a **server-truncated** snippet instead of
 * an embedded `Message`. The resource's own docblock records why the two exist
 * side by side and measures the gap (5,822 bytes against 1,531 for the same
 * five rows). Do not widen this to make it interchangeable with `Conversation`.
 *
 * Every relation-dependent key **falls back rather than vanishing**: `peer`,
 * `last_message_snippet` and `last_message_sender_id` are `null` and `unread`
 * is `false` when the loader did not supply the relation behind them. That is
 * deliberately not `whenLoaded()`, which drops the key and lets the client read
 * `undefined` as a value — so none of these is optional here.
 */
export type ConversationPreview = {
    id: number;
    /** Null only for a conversation with nobody else in it, which should not exist. */
    peer: UserSummary | null;
    /**
     * `conversations.last_message_at`, kept equal to the last undeleted
     * message's `created_at` by `App\Observers\MessageObserver`. An ISO string,
     * not a rendered "2 hours ago": the legacy payload carried
     * `diffForHumans()`, which bakes a locale and a reading time into text that
     * goes stale while the menu is open.
     */
    last_message_at: string | null;
    /** Truncated server-side to `petconnect.messaging.preview_snippet_length`. */
    last_message_snippet: string | null;
    /**
     * An id, not a sender object, because rendering a user would cost the
     * loader an avatar eager load. Compare it to `auth.user.id` to decide the
     * "You: " prefix; the peer is already on the row.
     */
    last_message_sender_id: number | null;
    unread: boolean;
};

/**
 * The whole of `conversations.previews`.
 *
 * Plain JSON, not an Inertia page object — the menu fetches it with `useHttp`.
 * There is **no `links` and no page meta**: the endpoint deliberately does not
 * paginate, because a dropdown does not page and publishing a `?page=2` invites
 * a "load more" the contract does not mean. Do not build one.
 *
 * `meta.unread_count` is the viewer's **whole inbox**, not a count of the rows
 * returned — `Actions\Messaging\CountUnreadConversations` runs it as its own
 * aggregate so that "5" cannot silently mean "5 of the 40 you have".
 */
export type ConversationPreviewList = {
    data: ConversationPreview[];
    meta: { unread_count: number };
};
