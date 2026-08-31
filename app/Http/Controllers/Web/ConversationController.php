<?php

namespace App\Http\Controllers\Web;

use App\Actions\Messaging\BuildInbox;
use App\Actions\Messaging\LoadConversationParticipants;
use App\Actions\Messaging\MarkConversationAsRead;
use App\Actions\Messaging\PaginateConversationMessages;
use App\Actions\Messaging\StartConversation;
use App\Concerns\MessageValidationRules;
use App\Http\Controllers\Controller;
use App\Http\Requests\Conversation\StoreConversationRequest;
use App\Http\Resources\Conversation\ConversationResource;
use App\Http\Resources\Message\MessageResource;
use App\Models\Conversation;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * The inbox and a single conversation.
 *
 * Every action authorizes through ConversationPolicy and then hands the work to
 * one Action or pipeline; no query or business rule lives here. The legacy
 * controller injected ConversationRepositoryInterface and
 * MessageRepositoryInterface straight into its constructor alongside two
 * services — which defeated the abstraction it was paying for and is one of the
 * recorded reasons this codebase has no repository layer (.ai/rules/app.md).
 *
 * ## `show` does not write, and that is a change
 *
 * The legacy `show` called `markAsRead` inside a GET, so rendering a page
 * mutated state and the endpoint was not idempotent. Reading a conversation now
 * advances nothing: the cursor moves on an explicit POST to
 * `conversations.read`, which the thread page fires once it has actually
 * rendered.
 *
 * The reason is not only purity. Inertia v3 prefetches links and performs
 * instant visits — real GET requests issued on hover and on intent — so a
 * state-mutating `show` would mark a thread read as the pointer crossed the
 * inbox row, and the unread badge would clear for messages nobody had opened.
 * Browsers and proxies are also entitled to repeat a GET. Splitting the write
 * out costs one extra request per thread opened and makes both halves say what
 * they do.
 *
 * `unread` is on the inbox payload, so the client knows which rows need the
 * POST and can skip it for a thread that is already read.
 *
 * ## Where each check lives
 *
 * ConversationPolicy decides about the acting user and their membership of the
 * thread. Whether the *recipient* of a new conversation will accept one is a
 * fact about somebody else that ConversationPolicy::create — which is handed no
 * conversation and no recipient — cannot see, and is decided in
 * Pipelines\Messages\StartDirectConversation\EnsureRecipientAccepts. The same
 * question about every message afterwards is MessagePolicy::create's and
 * Pipelines\Messages\Send\EnsureRecipientAccepts's; all of them ask
 * App\Models\User::acceptsMessagesFrom().
 *
 * ## Throttling is on the routes, not in the pipeline
 *
 * `conversations` is a named limiter defined in
 * AppServiceProvider::configureRateLimiters(), attached to `store` only. It is
 * middleware rather than a pipeline step on purpose: a rate limit's only
 * meaningful outcome is a 429 with Retry-After, which is transport, and
 * .ai/rules/pipelines.md keeps steps HTTP-free. The legacy app throttled
 * neither conversation creation nor message sending.
 *
 * ## Why a validation Concern is used by a controller
 *
 * `show` ships `messageBounds`, because the composer cannot enforce a ceiling
 * it has not been told: it defaulted to a hardcoded 5000 that matched
 * `petconnect.messaging.max_length` by coincidence and would have drifted the
 * moment either side moved. The bound is read through
 * App\Concerns\MessageValidationRules — the same accessor
 * StoreMessageRequest's `max:` rule is built from — rather than from `config()`
 * here, so there is one spelling of the key and one default, and the composer
 * and the validator cannot disagree. Web\ProfileController does the same with
 * ReviewValidationRules.
 */
class ConversationController extends Controller
{
    use MessageValidationRules;

    /**
     * A page of the signed-in user's conversations, most recent first.
     *
     * **Neither `messaging/Index.vue` nor `messaging/Show.vue` exists.** Both
     * have been outstanding since Phase 2d and are Phase 4's, together with
     * `profile/Show.vue` and the rewrite of `settings/Profile.vue`. The routes,
     * the payloads and their tests are real; the pages are not, so these props
     * are a contract to build against rather than one already in use.
     */
    public function index(Request $request, BuildInbox $buildInbox): Response
    {
        $this->authorize('viewAny', Conversation::class);

        return Inertia::render('messaging/Index', [
            'conversations' => ConversationResource::collection(
                $buildInbox->handle($request->user())
            ),
        ]);
    }

    /**
     * One conversation and the newest page of its messages.
     *
     * A pure read: see the class docblock for why marking the thread read is a
     * separate POST.
     */
    public function show(
        Conversation $conversation,
        LoadConversationParticipants $loadConversationParticipants,
        PaginateConversationMessages $paginateConversationMessages,
    ): Response {
        $this->authorize('view', $conversation);

        return Inertia::render('messaging/Show', [
            'conversation' => ConversationResource::make(
                $loadConversationParticipants->handle($conversation)
            ),
            'messages' => MessageResource::collection(
                $paginateConversationMessages->handle($conversation)
            ),
            'messageBounds' => $this->messageBounds(),
        ]);
    }

    /**
     * Open a direct conversation, optionally with a first message.
     *
     * Idempotent: pressing "Message" on a profile that already has a thread
     * reopens it rather than creating a second one, so this always redirects to
     * a conversation the user can read.
     */
    public function store(StoreConversationRequest $request, StartConversation $startConversation): RedirectResponse
    {
        $this->authorize('create', Conversation::class);

        $conversation = $startConversation->handle(
            initiator: $request->user(),
            recipientId: $request->recipientId(),
            initialMessage: $request->initialMessage(),
        );

        return to_route('conversations.show', $conversation);
    }

    /**
     * Move the signed-in participant's read cursor to now.
     *
     * The write that `show` used to do on a GET. Authorized as `view`, because
     * the right to move your own cursor in a thread is the right to read it —
     * there is no separate thing being changed, and read state is per user.
     */
    public function markAsRead(
        Request $request,
        Conversation $conversation,
        MarkConversationAsRead $markConversationAsRead,
    ): RedirectResponse {
        $this->authorize('view', $conversation);

        $markConversationAsRead->handle($conversation, $request->user());

        return back();
    }
}
