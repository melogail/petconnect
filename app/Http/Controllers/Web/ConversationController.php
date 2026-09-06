<?php

namespace App\Http\Controllers\Web;

use App\Actions\Messaging\BuildInbox;
use App\Actions\Messaging\CountUnreadConversations;
use App\Actions\Messaging\ListConversationPreviews;
use App\Actions\Messaging\LoadConversationParticipants;
use App\Actions\Messaging\MarkConversationAsRead;
use App\Actions\Messaging\PaginateConversationMessages;
use App\Actions\Messaging\StartConversation;
use App\Concerns\MessageValidationRules;
use App\Http\Controllers\Controller;
use App\Http\Requests\Conversation\StoreConversationRequest;
use App\Http\Resources\Conversation\ConversationPreviewResource;
use App\Http\Resources\Conversation\ConversationResource;
use App\Http\Resources\Message\MessageResource;
use App\Models\Conversation;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
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
 * ## `previews` is a route, not a shared Inertia prop
 *
 * The header's messages menu wants a handful of rows and one badge number on
 * whatever page the user is standing on. The legacy app supplied that from a
 * `messaging` prop — previews plus `unread_count` — built on **every** page
 * render, so the home feed, the pet form and the settings screen each paid for
 * an inbox nobody had opened. `previews` answers plain JSON instead, fetched
 * once per document load, which is the arrangement `notifications.index`
 * already has and the reason its docblock gives: a page that never opens the
 * menu costs no messaging query at all.
 *
 * It has its own resource and its own loader —
 * Conversation\ConversationPreviewResource out of
 * Actions\Messaging\ListConversationPreviews — and that is a decision made on
 * weight, not on exposure. It used to emit the same ConversationResource
 * `index` does, from the same Actions\Messaging\BuildInbox at a smaller page
 * size, on the argument that the extra keys cost no extra query. They do not;
 * they cost bytes, on the one endpoint in the application that every signed-in
 * visitor fetches once per document load whether or not they open the menu.
 * Measured on the development database, 5 conversations and no avatars: **5,822
 * bytes** through ConversationResource in a paginator, **1,531** through the
 * preview pair, and the gap widens with message length because MessageResource
 * emits `content` in full against a 5,000-character ceiling. The queries fell
 * with it, 8 to 5, because the dropdown needs neither `lastMessage.sender.media`
 * nor a paginator's COUNT.
 *
 * That is not the `Pet\PetCommentResource` mistake (.ai/rules/resources.md).
 * That one was an *identical* payload minted twice per domain; this is a
 * narrower projection of the same model with a different fetch profile, and
 * both classes say so in their own docblocks.
 *
 * The response is `{data, meta}` and carries **no pagination**: a dropdown does
 * not page, and publishing `links` pointing at a `?page=2` invited a "load
 * more" this endpoint does not mean. `data` is a bare limited collection; the
 * `data` key survives because `additional()` forces it back on even under
 * JsonResource::withoutWrapping().
 *
 * The badge is `meta.unread_count`, and it is the viewer's **total** rather
 * than a count of the five rows returned — a badge that says 5 when there are
 * forty is worse than no badge. Actions\Messaging\CountUnreadConversations is
 * its own aggregate for that reason and never re-runs the inbox query.
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
     * `messaging/Index.vue` and `messaging/Show.vue` **both exist now** and
     * both read these props — Index through `components/messaging/ConversationList.vue`,
     * Show through the thread components beside it. The note that used to sit
     * here, that neither page had been written and these props were "a contract
     * to build against rather than one already in use", was true through Phase
     * 2e and is not any more: renaming a key here now breaks a page, not only a
     * test.
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
     * The newest handful of the viewer's conversations, plus their unread
     * total, as JSON for the header's messages menu.
     *
     * Authorized exactly as `index` is, against the same policy method, and
     * that is a deliberate answer rather than a copied line. The notification
     * inbox is exempt from `$this->authorize()` because `notifications` is not
     * a policy-governed model and nothing there names a second party; a
     * conversation is both — it has a ConversationPolicy and it always has
     * somebody else in it. `viewAny` is the same "may you see conversations"
     * question `index` asks, and it must be asked here for the same reason it
     * is asked there: it is a decision recorded in a policy rather than the
     * absence of a check (.ai/rules/controllers.md). Leaving it off because
     * ListConversationPreviews reads `$user->conversations()` would make the
     * scoping an implementation detail of an Action rather than a stated rule.
     */
    public function previews(
        Request $request,
        ListConversationPreviews $listConversationPreviews,
        CountUnreadConversations $countUnreadConversations,
    ): AnonymousResourceCollection {
        $this->authorize('viewAny', Conversation::class);

        $user = $request->user();

        return ConversationPreviewResource::collection($listConversationPreviews->handle($user))
            ->additional(['meta' => ['unread_count' => $countUnreadConversations->handle($user)]]);
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
