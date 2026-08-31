<?php

namespace App\Policies;

use App\Models\Conversation;
use App\Models\User;

/**
 * Authorization for conversations.
 *
 * Every conversation route runs through this policy via $this->authorize() in
 * ConversationController, per .ai/rules/controllers.md. Nothing in this
 * vertical is public: unlike a pet listing or a comment thread, a conversation
 * has no guest-readable page, so every method takes a non-nullable User and the
 * routes sit behind `auth` and `verified`.
 *
 * The methods type hint User rather than Admin|User on purpose: Nova
 * authenticates App\Models\Admin on its own guard, so an Admin cannot be
 * authorised by this policy. The hint is a tripwire rather than a gate —
 * Gate::canBeCalledWithUser() short-circuits to true for any non-null user and
 * only reads the signature for guests, so an Admin reaching one of these raises
 * a TypeError rather than returning false — and the guard is what keeps them
 * apart. Reading private correspondence from a moderation screen is a decision
 * that belongs on the Nova resource, with its own audit trail.
 *
 * ## What changed from the legacy policy, and why
 *
 * `create` is the one that mattered. The legacy ConversationPolicy::create
 * returned a bare `true`, so any account that had confirmed nothing could open
 * a direct message with any other account whose id it could name — no
 * verification, no consent, no block list, no rate limit. That is an
 * unsolicited-DM spam vector, and the answer is not one check but three, in
 * three different places because they answer three different questions:
 *
 * 1. **Is the initiator allowed to start conversations at all?** Here:
 *    `isVerified()`, the same bar publishing a listing or a comment clears.
 * 2. **Will this recipient accept one from them?** Not here — it is a fact
 *    about another user, and this policy method has no conversation and no
 *    recipient to read it off. It is
 *    Pipelines\Messages\StartDirectConversation\EnsureRecipientAccepts, and for
 *    every message afterwards Pipelines\Messages\Send\EnsureRecipientAccepts
 *    plus MessagePolicy::create. All three ask
 *    App\Models\User::acceptsMessagesFrom(), which is where a block list lands
 *    when it is built.
 * 3. **How many, how fast?** Neither — the `conversations` rate limiter, 5 a
 *    minute and 30 a day, defined in AppServiceProvider::configureRateLimiters().
 *    A limit's only outcome is a 429 with Retry-After, which is transport.
 *
 * `update`, `delete`, `restore` and `forceDelete` are not ported. The legacy
 * policy defined all four as `return $this->view($user, $conversation)`, and
 * nothing called any of them: a conversation has no editable attributes (its
 * `type` is settled at creation and `last_message_at` belongs to
 * App\Observers\MessageObserver), and there is no route that deletes one, so
 * there is nothing to restore or force delete either. An unreachable policy
 * method is a claim about behaviour the app does not have — and `delete` as an
 * alias of `view` is a claim worth not inheriting: it says any participant may
 * destroy a correspondence for everybody in it, which is a product decision
 * nobody made.
 */
class ConversationPolicy
{
    /**
     * Reading your own inbox.
     *
     * True for any authenticated user, because the question this answers is
     * "may you see conversations" and not "whose" — App\Actions\Messaging\BuildInbox
     * reads the list off `$user->conversations()`, so a user is structurally
     * incapable of paging somebody else's. It is stated rather than omitted so
     * that "the inbox is open to every signed-in account" is a decision
     * recorded in a policy rather than the absence of a check.
     */
    public function viewAny(User $user): bool
    {
        return true;
    }

    /**
     * Reading one thread, and everything addressed to it: the messages
     * endpoint, and moving your own read cursor.
     *
     * Participation is the whole rule. It costs one `exists` query, which is
     * why it is asked once per request against a route-bound model and never
     * per row — MessageResource's `can_edit` and `can_delete` deliberately lean
     * on a query-free part of MessagePolicy instead.
     */
    public function view(User $user, Conversation $conversation): bool
    {
        return $conversation->hasParticipant($user);
    }

    /**
     * Opening a new conversation.
     *
     * Verification is the initiator-side half of closing the legacy
     * `return true`; see the class docblock for the other two halves and where
     * they live.
     */
    public function create(User $user): bool
    {
        return $user->isVerified();
    }
}
