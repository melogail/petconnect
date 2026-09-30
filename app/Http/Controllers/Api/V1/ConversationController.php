<?php

namespace App\Http\Controllers\Api\V1;

use App\Actions\Messaging\BuildInbox;
use App\Actions\Messaging\LoadConversationParticipants;
use App\Actions\Messaging\MarkConversationAsRead;
use App\Actions\Messaging\StartConversation;
use App\Concerns\ResolvesRequestUser;
use App\Http\Controllers\Controller;
use App\Http\Requests\Conversation\StoreConversationRequest;
use App\Http\Resources\Conversation\ConversationResource;
use App\Models\Conversation;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;

/**
 * The inbox, for the mobile app. `store` answers with the conversation
 * (new or the existing direct one between the pair, exactly as
 * StartConversation decides) so the app can open it without a second lookup.
 */
class ConversationController extends Controller
{
    use ResolvesRequestUser;

    public function index(Request $request, BuildInbox $buildInbox): AnonymousResourceCollection
    {
        $this->authorize('viewAny', Conversation::class);

        return ConversationResource::collection($buildInbox->handle($this->actor($request)));
    }

    public function show(
        Conversation $conversation,
        LoadConversationParticipants $loadConversationParticipants,
    ): ConversationResource {
        $this->authorize('view', $conversation);

        return ConversationResource::make($loadConversationParticipants->handle($conversation));
    }

    public function store(
        StoreConversationRequest $request,
        StartConversation $startConversation,
        LoadConversationParticipants $loadConversationParticipants,
    ): JsonResponse {
        $this->authorize('create', Conversation::class);

        $conversation = $startConversation->handle(
            initiator: $this->actor($request),
            recipientId: $request->recipientId(),
            initialMessage: $request->initialMessage(),
        );

        return ConversationResource::make($loadConversationParticipants->handle($conversation))
            ->response()
            ->setStatusCode(201);
    }

    public function markAsRead(
        Request $request,
        Conversation $conversation,
        MarkConversationAsRead $markConversationAsRead,
    ): Response {
        $this->authorize('view', $conversation);

        $markConversationAsRead->handle($conversation, $this->actor($request));

        return response()->noContent();
    }
}
