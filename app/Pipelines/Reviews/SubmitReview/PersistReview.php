<?php

namespace App\Pipelines\Reviews\SubmitReview;

use App\Exceptions\Reviews\AlreadyReviewed;
use App\Models\Review;
use Closure;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Database\UniqueConstraintViolationException;

/**
 * Write the review row.
 *
 * ## The integrity violation is where the duplicate rule is actually enforced
 *
 * EnsureNotAlreadyReviewed above is a check-then-write, which is a TOCTOU race
 * by construction. `reviews` carries unique (user_id, reviewable_type,
 * reviewable_id), and this catch is what turns the loser of that race — or of a
 * double-submitted form — from a 500 into the same field error the pre-check
 * produces.
 *
 * The catch is on Illuminate\Database\UniqueConstraintViolationException rather
 * than on QueryException plus a SQLSTATE comparison. That class is a
 * QueryException subclass the connection raises for exactly this case:
 * Connection::runQueryCallback() asks the driver's own isUniqueConstraintError()
 * — `UNIQUE constraint failed:` on SQLite, `Integrity constraint violation:
 * 1062` on MySQL — and promotes the exception before it leaves the connection.
 * Matching on the class therefore covers both drivers without this step knowing
 * either of their error codes, and without a broad `catch (QueryException)`
 * that would also swallow an unrelated failure as "already reviewed". There is
 * exactly one unique index on `reviews`, so the mapping is unambiguous.
 *
 * ## Nothing off the wire becomes an identity
 *
 * `user_id`, `reviewable_type` and `reviewable_id` are stamped from the
 * context, never forwarded from a validated request bag — all three are in
 * Review's #[Fillable] because factories fill them, so a controller passing
 * `validated()` into create() would let a caller file a review under someone
 * else's name. Only `rate` and `comment` came off the wire, and both went
 * through the Form Request and the sanitiser first.
 *
 * `reviewable_type` is written as the registered morph alias resolved from the
 * model class, so it stays correct if a case is renamed in
 * AppServiceProvider::configureMorphMap().
 *
 * No transaction: this is one INSERT, which is already atomic. The house rule
 * is that a transaction is opened by the Action when a flow writes more than
 * one row that must land together, never by a step around a single statement.
 *
 * ## The relations are set from the context, not reloaded
 *
 * `user` and `reviewable` are both already in hand — the author is the acting
 * user and the target was resolved by ResolveReviewable — so they are attached
 * with setRelation() for zero queries. NotifyReviewee reads exactly those two
 * (through ModelReviewedNotification's `loadMissing(['user', 'reviewable'])`,
 * which now finds them present), so the flow issues one INSERT and nothing
 * else.
 *
 * This used to be `$review->load('user.media')`, justified as "a caller may
 * serialise the return value straight back". No caller does:
 * ReviewController::store returns back() and the notification never touches
 * media, so the avatar cost two queries per submitted review for nobody. The
 * consequence is that **the returned Review is not serialisation-ready** — a
 * caller that hands it to ReviewResource would hit
 * Model::preventLazyLoading() on the author's media. Serialise reviews through
 * Actions\Reviews\ListReviews, which eager loads for that purpose.
 *
 * @throws AlreadyReviewed When the unique index refuses the row.
 */
class PersistReview
{
    public function handle(SubmitReviewContext $context, Closure $next): mixed
    {
        $reviewable = $context->reviewable();

        try {
            $review = Review::create([
                'user_id' => $context->author->getKey(),
                'rate' => $context->rate,
                'comment' => $context->comment(),
                'reviewable_type' => Relation::getMorphAlias($reviewable::class),
                'reviewable_id' => $reviewable->getKey(),
            ]);
        } catch (UniqueConstraintViolationException) {
            throw AlreadyReviewed::make();
        }

        $review->setRelation('user', $context->author);
        $review->setRelation('reviewable', $reviewable);

        $context->setReview($review);

        return $next($context);
    }
}
