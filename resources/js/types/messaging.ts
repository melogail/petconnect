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
