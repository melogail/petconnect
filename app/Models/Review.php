<?php

namespace App\Models;

use App\Concerns\HasReport;
use App\Contracts\Reportable;
use Database\Factories\ReviewFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * A rating (1-5) plus optional comment written by a user about a reviewable model.
 *
 * The 1-5 range is enforced by App\Concerns\ReviewValidationRules against
 * `petconnect.reviews.min_rate` / `max_rate`, not by the column: `rate` is an
 * unsignedTinyInteger, which accepts 0-255. The legacy app validated it with
 * nothing at all.
 *
 * One review per author per target is enforced by the database — `reviews` is
 * unique on (user_id, reviewable_type, reviewable_id) — and
 * Pipelines\Reviews\SubmitReview\PersistReview turns a violation into a field
 * error rather than a 500.
 *
 * Implements Reportable so the report flow can ask the review who is
 * answerable for it. See App\Contracts\Reportable.
 *
 * @property int $id
 * @property int $user_id
 * @property int $rate
 * @property string|null $comment
 * @property string $reviewable_type
 * @property int $reviewable_id
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable(['user_id', 'rate', 'comment', 'reviewable_type', 'reviewable_id'])]
class Review extends Model implements Reportable
{
    /** @use HasFactory<ReviewFactory> */
    use HasFactory, HasReport;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'rate' => 'integer',
        ];
    }

    /**
     * @return MorphTo<Model, $this>
     */
    public function reviewable(): MorphTo
    {
        return $this->morphTo();
    }

    /**
     * Resolve a route-bound review only while the thing it was written about is
     * still visible.
     *
     * `reviews/{review}` names a review and never its target, so the target's
     * visibility cannot come from the URL — the same shape that left a trashed
     * listing's comment thread readable and a soft-deleted conversation's
     * messages writable, both recorded in .ai/rules/app.md. It is decided once
     * here rather than in each of update and destroy.
     *
     * There is a second, sharper reason on this model. `reviewable_id` is a
     * morph column, so it carries no foreign key and nothing cascades: deleting
     * the *reviewed* user leaves every review about them in the table with a
     * dangling target. Those rows are unreachable through
     * Actions\Reviews\ListReviews, which reads through the target's own
     * relation, but `reviews/{review}` would happily bind one. A null relation
     * is a complete answer to "is the target still there", so returning null —
     * which makes ImplicitRouteBinding raise ModelNotFoundException — gives the
     * orphan the 404 it deserves.
     *
     * Today's only Reviewable is User, which does not soft delete, so this is a
     * guard against orphans rather than against a global scope. If a
     * soft-deleting model is ever added to App\Enums\Reviewable this override
     * already covers it — but note that it is only reached by
     * ImplicitRouteBinding's default resolver: a `withTrashed()` or scoped
     * binding on a review route would skip it silently. See .ai/rules/app.md.
     */
    public function resolveRouteBinding($value, $field = null): ?self
    {
        /** @var self|null $review */
        $review = parent::resolveRouteBinding($value, $field);

        if ($review === null) {
            return null;
        }

        $review->loadMissing('reviewable');

        return $review->reviewable === null ? null : $review;
    }

    /**
     * The review's author.
     *
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * The review's author is answerable for it, so they cannot report it.
     *
     * @return Collection<int, User>
     */
    public function reportSubjects(): Collection
    {
        $this->loadMissing('user');

        return collect([$this->user])->filter()->values();
    }
}
