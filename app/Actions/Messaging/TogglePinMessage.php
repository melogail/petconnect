<?php

namespace App\Actions\Messaging;

use App\Models\Message;
use App\Models\User;

/**
 * Pin a message, or unpin it if it is already pinned.
 *
 * `pinned_by` and `pinned_at` are deliberately outside Message's #[Fillable]
 * because pinning is privileged: a controller forwarding a validated request
 * bag into update() must not be able to set them, and who pinned a message is
 * the application's record, not the client's claim. They are therefore assigned
 * directly and saved rather than mass assigned — `Model::preventSilentlyDiscardingAttributes`
 * would throw on an update() bag holding them, which is the guardrail doing its
 * job (see .ai/rules/models.md).
 *
 * The two columns move together and are never half set: `is_pinned` is derived
 * from `pinned_at` alone, so a `pinned_by` left behind on unpin would be a
 * dangling attribution that nothing reads and everything about the row implies.
 *
 * Saving moves `updated_at`, and that is now harmless: it is the row's
 * last-write stamp and nothing reads it as a claim about the text.
 * `MessageResource::is_edited` used to be derived from it, so pinning a message
 * marked it "edited" for both participants; the edit trace is its own column
 * (`edited_at`, written only by Pipelines\Messages\Revise\PersistContent), so
 * this write no longer has to pretend it never happened.
 *
 * `pinned_by` records whoever pinned it, which is not necessarily the sender —
 * either participant may pin either side's message, which is what makes pinning
 * useful for marking out an address or a price somebody else quoted.
 * MessagePolicy::pin is the check that they are a participant.
 *
 * Toggle rather than separate pin and unpin Actions, for the reason
 * TogglePetStatus and ToggleLike are toggles: one control on the page, one
 * route, one method, and no way for the client to get out of step with the
 * stored state by asking for a transition that has already happened.
 */
class TogglePinMessage
{
    public function handle(Message $message, User $pinnedBy): Message
    {
        $isPinned = $message->pinned_at !== null;

        $message->pinned_at = $isPinned ? null : now();
        $message->pinned_by = $isPinned ? null : $pinnedBy->getKey();

        $message->save();

        return $message;
    }
}
