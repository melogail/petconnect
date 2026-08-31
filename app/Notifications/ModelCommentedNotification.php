<?php

namespace App\Notifications;

use App\Models\Comment;
use App\Models\Pet;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Str;

/**
 * Database notification telling a user that someone commented on something of
 * theirs, or replied to a comment they wrote.
 *
 * Sent from Pipelines\Comments\PublishComment\NotifyCommentable rather than
 * from a model observer, deliberately. Comments are seeded in bulk by
 * DatabaseSeeder and created wholesale by factories in the test suite; an
 * observer would turn every one of those into a notification row and make the
 * seeded database a poor mirror of a real one. The publish flow is the only
 * path a human comment arrives by, so it is the only path that notifies.
 *
 * Not queued, matching App\Observers\LikeObserver and ModelLikedNotification —
 * the app has no queue worker configured yet, and a database write inside the
 * request is cheaper than the sync driver pretending otherwise.
 */
class ModelCommentedNotification extends Notification
{
    /**
     * How much of the comment travels in the payload.
     */
    protected const EXCERPT_LENGTH = 120;

    public function __construct(
        public Comment $comment,
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
     * rendered text: a notification row outlives the reader's locale, so a
     * user who switches language must see their whole history in the new one
     * rather than text frozen at write time. See .ai/rules/notifications.md.
     *
     * `commenter_name` is null only when the author is gone (comments cascade
     * on user delete, so that is defensive); the client supplies its own
     * localized "someone" rather than the server persisting a key for it.
     *
     * @return array{
     *     comment_id: int,
     *     parent_id: int|null,
     *     commenter_id: int,
     *     commenter_name: string|null,
     *     commentable_type: string,
     *     commentable_id: int,
     *     commentable_name: string|null,
     *     excerpt: string,
     *     message_key: string,
     *     message_replace: array{name: string, subject: string},
     *     url: string|null,
     *     type: string
     * }
     */
    public function toArray(object $notifiable): array
    {
        $this->comment->loadMissing(['user', 'commentable']);

        $commenterName = $this->comment->user?->name;
        $commentable = $this->comment->commentable;
        $isReply = $this->comment->parent_id !== null;

        $messageKey = match (true) {
            $isReply => 'notifications.replied_to_comment',
            $commentable instanceof Pet => 'notifications.commented_on_pet',
            default => 'notifications.commented_generic',
        };

        return [
            'comment_id' => $this->comment->id,
            'parent_id' => $this->comment->parent_id,
            'commenter_id' => $this->comment->user_id,
            'commenter_name' => $commenterName,
            'commentable_type' => $this->comment->commentable_type,
            'commentable_id' => $this->comment->commentable_id,
            'commentable_name' => $commentable instanceof Pet ? $commentable->name : null,
            'excerpt' => Str::limit($this->comment->content, self::EXCERPT_LENGTH),
            'message_key' => $messageKey,
            'message_replace' => [
                'name' => (string) $commenterName,
                'subject' => $commentable instanceof Pet ? $commentable->name : '',
            ],
            'url' => $this->commentableUrl(),
            'type' => 'comment',
        ];
    }

    /**
     * Deep link to the page the thread lives on, or null while that route does
     * not yet exist.
     */
    protected function commentableUrl(): ?string
    {
        $commentable = $this->comment->commentable;

        return match (true) {
            $commentable instanceof Pet => Route::has('pets.show')
                ? route('pets.show', $commentable)
                : null,
            default => null,
        };
    }
}
