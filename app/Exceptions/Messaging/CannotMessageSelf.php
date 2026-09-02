<?php

namespace App\Exceptions\Messaging;

use Illuminate\Validation\ValidationException;

/**
 * Somebody tried to open a direct conversation with themselves.
 *
 * A direct conversation is defined by `conversations.betweenParticipants` as
 * two distinct rows in `conversation_user`, and the table is unique on
 * (conversation_id, user_id) — so a self-conversation cannot be attached twice
 * and would come out as a one-participant thread whose `otherParticipant()` is
 * null and whose every payload has a null peer. Rejecting it is cheaper than
 * teaching four resources to render half a conversation.
 *
 * It extends ValidationException because the abort is attributable to a
 * submitted field: `recipient_id` is a control the client picked and can pick
 * again. .ai/rules/pipelines.md allows the base for exactly that case, and
 * Laravel renders it as a 422 with `errors` for an XHR caller and a
 * redirect-back-with-errors for a form post, with no exception-render plumbing.
 *
 * StoreConversationRequest also rejects this shape: the `not_in` rule on
 * `recipient_id` in StoreConversationRequest::rules() excludes the authenticated
 * user's own id, and StoreConversationRequest::messages() carries the
 * `recipient_id.not_in` message, so an HTTP caller never reaches here. Read that
 * rule where it lives rather than a transcription of it here — the expression
 * quoted at this spot had already gone stale against the original. The step
 * remains because the pipeline is callable from a seeder, a console command or
 * another Action, none of which pass through a Form Request.
 */
class CannotMessageSelf extends ValidationException
{
    public static function make(): self
    {
        return self::withMessages([
            'recipient_id' => __('You cannot start a conversation with yourself.'),
        ]);
    }
}
