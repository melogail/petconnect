<?php

namespace App\Notifications;

use App\Models\Review;
use App\Models\User;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Str;

/**
 * Database notification telling a user that someone reviewed them.
 *
 * Sent from Pipelines\Reviews\SubmitReview\NotifyReviewee rather than from a
 * model observer, matching ModelCommentedNotification: reviews are seeded in
 * bulk by ReviewSeeder and created wholesale by factories in the test suite,
 * and an observer would turn every one of those into a notification row. The
 * submit flow is the only path a human review arrives by, so it is the only
 * path that notifies. The legacy app notified nobody at all — a user learned
 * they had been rated in public by visiting their own profile.
 *
 * Not queued, matching every other notification here: the app has no queue
 * worker configured yet.
 */
class ModelReviewedNotification extends Notification
{
    /**
     * How much of the review comment travels in the payload.
     */
    protected const EXCERPT_LENGTH = 120;

    public function __construct(
        public Review $review,
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
     * `reviewer_name` is null only when the author is gone (reviews cascade on
     * user delete, so that is defensive); the client supplies its own localized
     * "someone" rather than the server persisting a key for it.
     *
     * `excerpt` is null for a rating with no words, which is a legitimate
     * review — the client renders the stars alone in that case.
     *
     * @return array{
     *     review_id: int,
     *     reviewer_id: int,
     *     reviewer_name: string|null,
     *     reviewable_type: string,
     *     reviewable_id: int,
     *     rate: int,
     *     excerpt: string|null,
     *     message_key: string,
     *     message_replace: array{name: string, rate: string},
     *     url: string|null,
     *     type: string
     * }
     */
    public function toArray(object $notifiable): array
    {
        $this->review->loadMissing(['user', 'reviewable']);

        $reviewerName = $this->review->user?->name;

        return [
            'review_id' => $this->review->id,
            'reviewer_id' => $this->review->user_id,
            'reviewer_name' => $reviewerName,
            'reviewable_type' => $this->review->reviewable_type,
            'reviewable_id' => $this->review->reviewable_id,
            'rate' => $this->review->rate,
            'excerpt' => $this->review->comment === null
                ? null
                : Str::limit($this->review->comment, self::EXCERPT_LENGTH),
            'message_key' => 'notifications.reviewed_you',
            'message_replace' => [
                'name' => (string) $reviewerName,
                'rate' => (string) $this->review->rate,
            ],
            'url' => $this->reviewableUrl(),
            'type' => 'review',
        ];
    }

    /**
     * Deep link to the page the review is shown on, or null when that route
     * does not exist.
     *
     * This used to say the profile vertical was not ported and `profile.show`
     * was absent, so the link was always null. Phase 2e landed that route:
     * `Route::has()` is true today and a user review **does** carry a deep
     * link.
     *
     * The guard stays, and is not now-dead code. A stored notification row
     * outlives the deploy that wrote it, `App\Enums\Reviewable` is expected to
     * grow past its single `User` case, and the `default` arm already returns
     * null for any reviewable whose page does not exist — Route::has() is the
     * same question asked about the one type that does. Checked rather than
     * assumed, the same way ModelCommentedNotification handles it.
     */
    protected function reviewableUrl(): ?string
    {
        $reviewable = $this->review->reviewable;

        return match (true) {
            $reviewable instanceof User => Route::has('profile.show')
                ? route('profile.show', $reviewable)
                : null,
            default => null,
        };
    }
}
