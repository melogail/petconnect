<?php

namespace App\Http\Controllers\Web;

use App\Actions\Messaging\DeleteMessage;
use App\Actions\Messaging\PaginateConversationMessages;
use App\Actions\Messaging\SendMessage;
use App\Actions\Messaging\TogglePinMessage;
use App\Actions\Messaging\UpdateMessage;
use App\Http\Controllers\Controller;
use App\Http\Requests\Message\StoreMessageRequest;
use App\Http\Requests\Message\UpdateMessageRequest;
use App\Http\Resources\Message\MessageResource;
use App\Models\Conversation;
use App\Models\Message;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Inertia\Inertia;

/**
 * Messages inside a conversation.
 *
 * Every action authorizes through MessagePolicy — or, for reading,
 * ConversationPolicy — and then hands the work to one Action or pipeline; no
 * query or business rule lives here.
 *
 * ## Authorization is visible at the call site now
 *
 * The legacy controller called a policy in one of its three actions.
 * `store` and `update` were authorized from inside StoreMessageRequest and
 * UpdateMessageRequest, so the controller showed no check at all and the answer
 * to "who can send a message" lived in a file the controller never mentions.
 * All five actions here call `$this->authorize()`, per
 * .ai/rules/controllers.md, and the Form Requests validate only.
 *
 * `store` passes the conversation as a policy argument —
 * `authorize('create', [Message::class, $conversation])` — because there is no
 * message yet to read the conversation off.
 *
 * ## One endpoint returns JSON rather than an Inertia page
 *
 * `index` is a data endpoint, not a page: `conversations.show` already ships
 * the newest page of the thread, and this is how the rest is paged in without a
 * visit, the same split `comments.index` has. `MessageResource::collection($paginator)`
 * gives the client `data`/`links`/`meta` — paginated collections keep their
 * envelope even though JsonResource::withoutWrapping() is on application-wide
 * (see .ai/rules/resources.md). Every write redirects back, because those are
 * posted from the thread page and their result belongs in that page's props.
 *
 * ## Visibility of the parent conversation
 *
 * `update`, `destroy` and `pin` name only a message, and their URLs never
 * mention the conversation it belongs to — so the conversation's visibility
 * cannot come from the URL. It comes from Message::resolveRouteBinding(), which
 * refuses to bind a message whose conversation is soft-deleted, exactly as
 * Comment::resolveRouteBinding() does for a comment on a retired listing. Fixed
 * once on the model, not once per route.
 */
class MessageController extends Controller
{
    /**
     * A page of one conversation's messages, newest first.
     */
    public function index(
        Conversation $conversation,
        PaginateConversationMessages $paginateConversationMessages,
    ): AnonymousResourceCollection {
        $this->authorize('view', $conversation);

        return MessageResource::collection($paginateConversationMessages->handle($conversation));
    }

    /**
     * Send a message.
     */
    public function store(
        StoreMessageRequest $request,
        Conversation $conversation,
        SendMessage $sendMessage,
    ): RedirectResponse {
        $this->authorize('create', [Message::class, $conversation]);

        $sendMessage->handle(
            conversation: $conversation,
            sender: $request->user(),
            content: $request->content(),
            type: $request->type(),
        );

        return back();
    }

    /**
     * Apply an edit to a message.
     *
     * A 403 here can mean the edit window has closed rather than that the user
     * is the wrong person — see MessagePolicy::update.
     */
    public function update(
        UpdateMessageRequest $request,
        Message $message,
        UpdateMessage $updateMessage,
    ): RedirectResponse {
        $this->authorize('update', $message);

        $updateMessage->handle($message, $request->content());

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Message updated.')]);

        return back();
    }

    /**
     * Withdraw a message.
     *
     * A soft delete: the row stays for moderation, and
     * App\Observers\MessageObserver walks `conversations.last_message_at` back
     * to whatever is left.
     */
    public function destroy(Message $message, DeleteMessage $deleteMessage): RedirectResponse
    {
        $this->authorize('delete', $message);

        $deleteMessage->handle($message);

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Message removed.')]);

        return back();
    }

    /**
     * Pin a message, or unpin it if it is already pinned.
     *
     * One route and one method rather than a pin and an unpin, so the client
     * cannot ask for a transition that has already happened — the same shape as
     * `pets.status.toggle` and the two like endpoints.
     */
    public function togglePin(
        Request $request,
        Message $message,
        TogglePinMessage $togglePinMessage,
    ): RedirectResponse {
        $this->authorize('pin', $message);

        $togglePinMessage->handle($message, $request->user());

        return back();
    }
}
