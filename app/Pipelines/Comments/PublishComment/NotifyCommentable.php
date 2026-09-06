<?php

namespace App\Pipelines\Comments\PublishComment;

use App\Models\User;
use App\Notifications\ModelCommentedNotification;
use Closure;
use Illuminate\Support\Collection;

/**
 * Tell the people who should hear about the comment that was just written.
 *
 * Who that is depends on what was written, and only on that:
 * - a reply notifies the author of the comment it answers, who is the person in
 *   the conversation, not the listing owner watching from a distance;
 * - a top-level comment notifies whoever the target names through
 *   App\Contracts\Commentable::commentNotificationRecipients() — the owner, for
 *   a pet.
 *
 * The author is filtered out of the result, so commenting on your own listing
 * or answering your own comment is silent. That mirrors LikeObserver, which
 * drops self-likes for the same reason.
 *
 * Runs last, after PersistComment, so a notification is never sent for a row
 * that failed to write. The legacy app sent nothing at all here: a pet owner
 * had no way of learning that somebody had asked a question about their
 * listing.
 */
class NotifyCommentable
{
    public function handle(PublishCommentContext $context, Closure $next): mixed
    {
        $comment = $context->comment();

        $this->recipients($context)
            ->filter(fn (?User $recipient): bool => $recipient !== null && ! $recipient->is($context->author))
            ->unique(fn (User $recipient): mixed => $recipient->getKey())
            ->each(fn (User $recipient) => $recipient->notify(new ModelCommentedNotification($comment)));

        return $next($context);
    }

    /**
     * @return Collection<int, User>
     */
    protected function recipients(PublishCommentContext $context): Collection
    {
        $parent = $context->parent();

        if ($parent !== null) {
            $parent->loadMissing('user');

            return collect([$parent->user])->filter()->values();
        }

        return $context->commentableAsThread()->commentNotificationRecipients();
    }
}
