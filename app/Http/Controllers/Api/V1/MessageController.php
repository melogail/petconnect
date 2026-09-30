<?php

namespace App\Http\Controllers\Api\V1;

use App\Actions\Messaging\DeleteMessage;
use App\Actions\Messaging\PaginateConversationMessages;
use App\Actions\Messaging\SendMessage;
use App\Actions\Messaging\TogglePinMessage;
use App\Actions\Messaging\UpdateMessage;
use App\Concerns\ResolvesRequestUser;
use App\Http\Controllers\Controller;
use App\Http\Requests\Message\StoreMessageRequest;
use App\Http\Requests\Message\UpdateMessageRequest;
use App\Http\Resources\Message\MessageResource;
use App\Models\Conversation;
use App\Models\Message;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;

/**
 * Messages, for the mobile app — Web\MessageController with JSON answers.
 * `index` is newest-first and paginated, so the thread screen loads the
 * latest page and pulls older pages as the user scrolls up.
 */
class MessageController extends Controller
{
    use ResolvesRequestUser;

    public function index(
        Conversation $conversation,
        PaginateConversationMessages $paginateConversationMessages,
    ): AnonymousResourceCollection {
        $this->authorize('view', $conversation);

        return MessageResource::collection($paginateConversationMessages->handle($conversation));
    }

    public function store(
        StoreMessageRequest $request,
        Conversation $conversation,
        SendMessage $sendMessage,
    ): JsonResponse {
        $this->authorize('create', [Message::class, $conversation]);

        $message = $sendMessage->handle(
            conversation: $conversation,
            sender: $this->actor($request),
            content: $request->content(),
            type: $request->type(),
        );

        return MessageResource::make($message->loadMissing(['sender.media', 'conversation']))
            ->response()
            ->setStatusCode(201);
    }

    public function update(
        UpdateMessageRequest $request,
        Message $message,
        UpdateMessage $updateMessage,
    ): MessageResource {
        $this->authorize('update', $message);

        $updateMessage->handle($message, $request->content());

        return MessageResource::make($message->loadMissing('sender.media'));
    }

    public function destroy(Message $message, DeleteMessage $deleteMessage): Response
    {
        $this->authorize('delete', $message);

        $deleteMessage->handle($message);

        return response()->noContent();
    }

    public function togglePin(
        Request $request,
        Message $message,
        TogglePinMessage $togglePinMessage,
    ): MessageResource {
        $this->authorize('pin', $message);

        $togglePinMessage->handle($message, $this->actor($request));

        return MessageResource::make($message->loadMissing('sender.media'));
    }
}
