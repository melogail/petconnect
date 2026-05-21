<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreMessageRequest;
use App\Http\Requests\UpdateMessageRequest;
use App\Models\Conversation;
use App\Models\Message;
use App\Services\MessageService;
use Illuminate\Http\RedirectResponse;

class MessageController extends Controller
{
    public function __construct(protected MessageService $messageService) {}

    public function store(StoreMessageRequest $request, Conversation $conversation): RedirectResponse
    {
        $validated = $request->validated();

        $this->messageService->send(
            $conversation,
            $request->user(),
            $validated['content'],
            $validated['type'] ?? null,
        );

        return redirect()->route('conversations.show', $conversation);
    }

    public function update(UpdateMessageRequest $request, Message $message): RedirectResponse
    {
        $this->messageService->update($message, $request->validated('content'));

        return redirect()->route('conversations.show', $message->conversation_id)
            ->with('success', 'Message updated.');
    }

    public function destroy(Message $message): RedirectResponse
    {
        $this->authorize('delete', $message);

        $conversation = $message->conversation;
        $this->messageService->delete($message);

        return redirect()->route('conversations.show', $conversation)
            ->with('success', 'Message deleted.');
    }
}
