<?php

namespace App\Exceptions\Messaging;

use Illuminate\Validation\ValidationException;

/**
 * The recipient is not accepting a direct conversation from this initiator.
 *
 * This is the abort of Pipelines\Messages\StartDirectConversation\
 * EnsureRecipientAccepts, which is half of the seam the legacy app had nowhere:
 * its ConversationPolicy::create returned a bare `true`, so any verified
 * account could open a DM with any other account, with no consent check, no
 * block list and no rate limit — an unsolicited-message spam vector with the
 * recipient's inbox as the target.
 *
 * Half, because opening a thread is only one of the two ways a message reaches
 * somebody. The other is `messages.store` into a thread that already exists,
 * which never runs that step; its consent check is MessagePolicy::create over
 * HTTP and Pipelines\Messages\Send\EnsureRecipientAccepts as the invariant, and
 * a refusal there raises RecipientNotAcceptingMessages rather than this class.
 *
 * The rule is stated once, in App\Models\User::acceptsMessagesFrom(), and every
 * seam asks that method. A recipient-side block list and per-recipient message
 * settings are not built (they are their own vertical, with their own table and
 * their own UI), but when they arrive they are new checks in that one method
 * rather than a new decision in a step, a controller or a policy.
 *
 * **Deactivation is not one of the reasons this exception covers.** A
 * deactivated account is not addressable by id at all: Actions\Messaging\
 * StartConversation resolves the recipient through User::resolveRouteBinding(),
 * which refuses it with a ModelNotFoundException — a 404 — before the pipeline
 * runs, exactly as profile.show answers ("not addressable by id, anywhere",
 * .ai/rules/app.md). That is deliberately earlier and louder than a refusal
 * here, and it is the same answer an id that was never issued gets, which is
 * what keeps that pair from being an existence oracle.
 *
 * What is left for this exception is **consent**: the recipient exists, is
 * addressable, and declines this initiator. Among those reasons the message is
 * deliberately identical. "This person has blocked you" and "this person takes
 * messages from people they follow only" are different facts about another
 * user, and a stranger who can tell them apart by probing has learned something
 * about a person who did not choose to tell them. The contract is that no
 * consent reason is distinguishable from another — not that consent is
 * indistinguishable from non-existence, which resolution has already answered.
 *
 * Today `acceptsMessagesFrom()` is `return $this->isActive();` and nothing else,
 * so no consent reason can currently fire and this exception is unreachable
 * through the Action. It is the seam those reasons land on, not dead code — see
 * Pipelines\Messages\StartDirectConversation\EnsureRecipientAccepts.
 *
 * It extends ValidationException for the reason CannotMessageSelf does: the
 * abort is attributable to `recipient_id`, a control the client chose and can
 * choose again, which is the one case .ai/rules/pipelines.md sanctions for that
 * base.
 */
class ConversationNotPermitted extends ValidationException
{
    public static function make(): self
    {
        return self::withMessages([
            'recipient_id' => __('You cannot start a conversation with this person.'),
        ]);
    }
}
