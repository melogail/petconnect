<?php

namespace App\Notifications;

use App\Models\Message;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Str;

/**
 * Database notification telling a user that a message arrived in one of their
 * conversations.
 *
 * Sent from Pipelines\Messages\Send\NotifyRecipient rather than from
 * App\Observers\MessageObserver, deliberately and for the reason
 * ModelCommentedNotification gives: messages are seeded in bulk by
 * DatabaseSeeder and created wholesale by factories in the test suite, and an
 * observer would turn every one of those into a notification row, making the
 * seeded database a poor mirror of a real one. The send flow is the only path a
 * human message arrives by, so it is the only path that notifies. The observer
 * next door keeps `conversations.last_message_at` correct for all of them,
 * which is a different job.
 *
 * The legacy app sent nothing here at all: a user learned they had mail only by
 * opening the inbox.
 *
 * Not queued, matching every other notification in this application — there is
 * no queue worker configured yet and the sync driver would only pretend.
 */
class NewMessageNotification extends Notification
{
    /**
     * How much of the message travels in the payload.
     *
     * Deliberately an excerpt and not the message. A notification row is read
     * from a bell menu that has no idea whether the reader is the intended
     * recipient of a private correspondence — it is the same row after the
     * conversation is deleted — so it carries enough to recognise the thread
     * and no more. The full text is behind ConversationPolicy::view.
     */
    protected const EXCERPT_LENGTH = 120;

    public function __construct(
        public Message $message,
    ) {}

    /**
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['database'];
    }

    /**
     * The payload carries translation keys and their replacements, never
     * rendered text: a notification row outlives the reader's locale, so a user
     * who switches language must see their whole history in the new one rather
     * than text frozen at write time. See .ai/rules/notifications.md.
     *
     * `sender_name` is null only when the sender's account is gone (messages
     * cascade on user delete, so that is defensive); the client supplies its
     * own localized "someone" rather than the server persisting a key for it.
     *
     * @return array{
     *     message_id: int,
     *     conversation_id: int,
     *     sender_id: int,
     *     sender_name: string|null,
     *     excerpt: string,
     *     message_key: string,
     *     message_replace: array{name: string},
     *     url: string|null,
     *     type: string
     * }
     */
    public function toArray(object $notifiable): array
    {
        $this->message->loadMissing('sender');

        $senderName = $this->message->sender?->name;

        return [
            'message_id' => $this->message->id,
            'conversation_id' => $this->message->conversation_id,
            'sender_id' => $this->message->sender_id,
            'sender_name' => $senderName,
            'excerpt' => Str::limit($this->message->content, self::EXCERPT_LENGTH),
            'message_key' => 'notifications.new_message',
            'message_replace' => [
                'name' => (string) $senderName,
            ],
            'url' => $this->conversationUrl(),
            'type' => 'message',
        ];
    }

    /**
     * Deep link to the thread, or null while that route does not yet exist.
     */
    protected function conversationUrl(): ?string
    {
        return Route::has('conversations.show')
            ? route('conversations.show', $this->message->conversation_id)
            : null;
    }
}
