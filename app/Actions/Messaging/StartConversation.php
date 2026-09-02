<?php

namespace App\Actions\Messaging;

use App\Models\Conversation;
use App\Models\User;
use App\Pipelines\Messages\StartDirectConversation\AttachParticipants;
use App\Pipelines\Messages\StartDirectConversation\CreateConversationRecord;
use App\Pipelines\Messages\StartDirectConversation\EnsureDistinctParticipants;
use App\Pipelines\Messages\StartDirectConversation\EnsureRecipientAccepts;
use App\Pipelines\Messages\StartDirectConversation\FindExistingDirectConversation;
use App\Pipelines\Messages\StartDirectConversation\MarkReadForInitiator;
use App\Pipelines\Messages\StartDirectConversation\SendInitialMessage;
use App\Pipelines\Messages\StartDirectConversation\StartDirectConversationContext;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Pipeline\Pipeline;
use Illuminate\Support\Facades\DB;

/**
 * Open a direct conversation with another user, optionally with a first
 * message.
 *
 * Idempotent by design: two people can press "Message" on each other's profile
 * at the same moment, and `conversations` has no unique index that could stop a
 * second thread between the same pair — the pair lives in `conversation_user`,
 * whose unique index is per (conversation, user) and cannot express "these two,
 * once". The flow therefore looks first and reopens what it finds, which is
 * also what the button promises: pressing it twice lands on the same thread.
 *
 * The transaction is opened here rather than inside a step, because it has to
 * span several of them: the conversation row, both participant rows, the
 * opening message and the initiator's read cursor either all land or none do.
 * Anything narrower can leave an empty thread in two inboxes, or a thread with
 * one participant that nobody can reply to. The legacy service wrapped only
 * find-or-create and left the opening message outside the transaction entirely.
 *
 * Ordering, and why it is not obvious:
 * - EnsureDistinctParticipants and EnsureRecipientAccepts come first
 *   because a refused conversation must not have created a row to roll back.
 * - Find before Create, so an existing thread is reopened instead of forked.
 * - Attach before SendInitialMessage, because Send\EnsureParticipant asks
 *   `conversation_user` whether the sender belongs here and the answer has to
 *   already be yes.
 * - MarkReadForInitiator last, so the cursor covers the message just sent.
 *
 * @see EnsureRecipientAccepts for the consent seam the legacy
 *      ConversationPolicy::create (`return true`) did not have. It is half of
 *      it: it covers a thread opened with no message, and the identically named
 *      Pipelines\Messages\Send\EnsureRecipientAccepts — which SendInitialMessage
 *      reaches through App\Actions\Messaging\SendMessage — covers every message,
 *      here and in threads that already exist. Both ask
 *      App\Models\User::acceptsMessagesFrom(), which is where the rule lives.
 */
class StartConversation
{
    public function __construct(private readonly Pipeline $pipeline) {}

    /**
     * @param  int  $recipientId  The user to open the conversation with. Taken as
     *                            an id rather than a model because the caller is
     *                            a controller, which does not compose queries —
     *                            resolving it here keeps the single query for it
     *                            in the Action layer, and turns an account that
     *                            went away between rendering the profile and
     *                            pressing the button into a 404 rather than an
     *                            integrity error inside the transaction.
     *
     * @throws ModelNotFoundException<User> When the recipient cannot be addressed
     *                                      by that id — deleted or deactivated,
     *                                      indistinguishably.
     */
    public function handle(User $initiator, int $recipientId, ?string $initialMessage = null): Conversation
    {
        $recipient = $this->resolveRecipient($recipientId);

        $context = new StartDirectConversationContext(
            initiator: $initiator,
            recipient: $recipient,
            initialMessageContent: $initialMessage,
        );

        return DB::transaction(fn (): Conversation => $this->pipeline
            ->send($context)
            ->through([
                EnsureDistinctParticipants::class,
                EnsureRecipientAccepts::class,
                FindExistingDirectConversation::class,
                CreateConversationRecord::class,
                AttachParticipants::class,
                SendInitialMessage::class,
                MarkReadForInitiator::class,
            ])
            ->then(fn (StartDirectConversationContext $completed): Conversation => $completed->conversation()));
    }

    /**
     * Resolve the recipient the way a `{user}` route parameter would.
     *
     * A bare `User::query()->findOrFail()` here was a weak existence oracle. It
     * is Eloquent's default lookup, so it answered "yes" for a **deactivated**
     * account — which `Rule::exists('users', 'id')` also lets through, the
     * column being untouched by deactivation — and the flow then ran on to
     * EnsureRecipientAccepts and aborted there. A **nonexistent** id aborted
     * here instead, with a different exception and a different status, so an
     * unprivileged caller could tell a deactivated account apart from an id
     * that was never issued and thereby enumerate which ids exist.
     *
     * Going through `resolveRouteBinding()` closes it: `User` overrides that
     * method to refuse a deactivated account (see its docblock), so both cases
     * now produce the same ModelNotFoundException from the same line, before
     * any step runs and before the transaction opens. That is exactly the
     * mechanism `App\Concerns\ResolvesMorphTarget::findVisibleOrFail()` uses to
     * close the identical hole in the reviews and reports verticals — "not
     * addressable by id, anywhere" (.ai/rules/app.md).
     *
     * The enum's `findVisibleOrFail()` is deliberately *not* reused. It hangs
     * off `Reviewable`/`Reportable`, whose job is to whitelist a morph target
     * named in a URL; a messaging recipient is neither polymorphic nor
     * whitelisted, so borrowing one of those enums would make this flow depend
     * on the reviews taxonomy to look up a user. The technique is shared, the
     * enum is not — and both land on `User::resolveRouteBinding()` regardless,
     * which is where the one answer lives.
     *
     * Costs the same one query as the `findOrFail` it replaces, and changes
     * nothing else: self-messaging is still caught by EnsureDistinctParticipants
     * (the initiator holds a session, so their own id resolves), recipient
     * consent is still `User::acceptsMessagesFrom()` in EnsureRecipientAccepts,
     * and an existing thread is still reopened rather than forked.
     *
     * @throws ModelNotFoundException<User>
     */
    private function resolveRecipient(int $recipientId): User
    {
        $recipient = (new User)->resolveRouteBinding($recipientId);

        if (! $recipient instanceof User) {
            throw (new ModelNotFoundException)->setModel(User::class, [$recipientId]);
        }

        return $recipient;
    }
}
