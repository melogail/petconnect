<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreConversationRequest;
use App\Http\Resources\ConversationResource;
use App\Http\Resources\MessageResource;
use App\Models\Conversation;
use App\Models\User;
use App\Repositories\Interfaces\ConversationRepositoryInterface;
use App\Repositories\Interfaces\MessageRepositoryInterface;
use App\Services\ConversationService;
use App\Services\MessagingInboxService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class ConversationController extends Controller
{
    public function __construct(
        protected ConversationService $conversationService,
        protected ConversationRepositoryInterface $conversations,
        protected MessageRepositoryInterface $messages,
        protected MessagingInboxService $messagingInbox,
    ) {}

    public function index(Request $request): Response
    {
        $this->authorize('viewAny', Conversation::class);

        return Inertia::render('messaging/Index', [
            'inbox' => $this->messagingInbox->inboxFor($request->user()),
        ]);
    }

    public function show(Request $request, Conversation $conversation): Response
    {
        $this->authorize('view', $conversation);

        $this->conversationService->markAsRead($conversation, $request->user());
        $messages = $this->messages->paginateForConversation($conversation);

        return Inertia::render('messaging/Show', [
            'conversation' => ConversationResource::make($this->conversations->loadParticipants($conversation))->resolve($request),
            'messages' => MessageResource::collection($messages),
        ]);
    }

    public function store(StoreConversationRequest $request): RedirectResponse
    {
        $this->authorize('create', Conversation::class);

        $validated = $request->validated();
        $other = User::query()->findOrFail($validated['other_user_id']);
        $conversation = $this->conversationService->startDirectConversation(
            $request->user(),
            $other,
            $validated['initial_message'] ?? null,
        );

        return redirect()->route('conversations.show', $conversation);
    }

    public function markAsRead(Request $request, Conversation $conversation): RedirectResponse
    {
        $this->authorize('view', $conversation);

        $this->conversationService->markAsRead($conversation, $request->user());

        return back();
    }
}
