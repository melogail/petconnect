<?php

namespace App\Pipelines\Messages\StartDirectConversation;

use App\Exceptions\Messaging\ConversationNotPermitted;
use Closure;

/**
 * Decide whether this recipient will accept a direct conversation from this
 * initiator.
 *
 * The consent seam the legacy app had nowhere: its ConversationPolicy::create
 * returned a bare `true`, so any verified account could open a DM with any
 * other account it could name an id for — no consent, no block list, no rate
 * limit. That is closed in four parts now: this step, the identically named
 * Send\EnsureRecipientAccepts on the message path, a policy that requires a
 * verified initiator, and a `conversations` rate limiter on the route
 * (5/minute, 30/day).
 *
 * ## Why this step still exists when Send\EnsureRecipientAccepts does
 *
 * They cover different halves of the same flow rather than the same ground
 * twice. `initial_message` is optional — the profile button offers "open a
 * thread" as well as "say something" — and a conversation opened empty never
 * reaches the send flow, so without this step a blocked or unwelcome initiator
 * could still put a thread in somebody's inbox. Conversely this step alone was
 * the bug: it only ever runs when a *new* thread is opened, so it can say
 * nothing about the messages posted into a thread that already exists.
 *
 * Neither step states the rule. Both ask User::acceptsMessagesFrom() — this one
 * directly, because there is no conversation yet to ask about — so a block list
 * or per-recipient message settings land in that one method and take effect on
 * both paths without either being edited.
 *
 * It aborts with ConversationNotPermitted rather than the send flow's
 * RecipientNotAcceptingMessages because here the abort *is* attributable to a
 * submitted field: `recipient_id` is a control the client picked and can pick
 * again, which is the one case .ai/rules/pipelines.md sanctions for a
 * ValidationException base.
 *
 * Note it does not authorize the *initiator* — that is
 * ConversationPolicy::create, called with $this->authorize() in
 * ConversationController per .ai/rules/controllers.md. This step decides about
 * the other party, which no policy on Conversation can see.
 *
 * @throws ConversationNotPermitted
 */
class EnsureRecipientAccepts
{
    public function handle(StartDirectConversationContext $context, Closure $next): mixed
    {
        if (! $context->recipient->acceptsMessagesFrom($context->initiator)) {
            throw ConversationNotPermitted::make();
        }

        return $next($context);
    }
}
