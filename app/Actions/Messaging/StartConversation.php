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
     *                            in the Action layer, and `findOrFail` turns an
     *                            account deleted between rendering the profile
     *                            and pressing the button into a 404 rather than
     *                            an integrity error inside the transaction.
     *
     * @throws ModelNotFoundException<User> When the recipient no longer exists.
     */
    public function handle(User $initiator, int $recipientId, ?string $initialMessage = null): Conversation
    {
        $recipient = User::query()->findOrFail($recipientId);

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
}
