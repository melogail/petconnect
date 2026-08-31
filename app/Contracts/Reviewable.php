<?php

namespace App\Contracts;

use App\Models\Review;
use App\Models\User;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Support\Collection;

/**
 * A model that users are able to review.
 *
 * App\Concerns\HasReviews supplies reviews() and the withReviewStats() scope;
 * implementing this interface is what makes the model a legal target of the
 * submit flow. The split is the same one App\Contracts\Commentable has against
 * App\Concerns\HasComments, and App\Contracts\Likeable against
 * App\Concerns\HasLikes: the trait is the storage, the interface is the
 * declaration that this model really is something a stranger may rate in
 * public, and knows who that rating is about.
 *
 * App\Enums\Reviewable is the *input* whitelist — it maps the string a URL may
 * carry to a model class. This interface is the *model-side* invariant.
 * Pipelines\Reviews\SubmitReview\SubmitReviewContext::reviewableAsSubject()
 * checks it on the write path and Actions\Reviews\ListReviews on the read path,
 * so adding an enum case for a model that never opted in fails loudly instead
 * of writing review rows onto something with no rating to read back.
 *
 * (Same-named, different namespace, on purpose: the enum names the wire value,
 * the contract names the capability. The enum stops at ResolveReviewable and
 * the contract starts at the context.)
 */
interface Reviewable
{
    /**
     * @return MorphMany<Review, static>
     */
    public function reviews(): MorphMany;

    /**
     * The users a review filed here is *about*.
     *
     * One method answers two questions the flow asks, because they are the same
     * question: these are the people notified when a review lands
     * (SubmitReview\NotifyReviewee) and the people who may not file one
     * (SubmitReview\EnsureNotSelfReview). Splitting them would let the two
     * drift, and a model whose owner could not be told about a review but could
     * still write one about itself is exactly the pair that must never
     * disagree.
     *
     * For a User that is the user themselves. For a model owned by somebody —
     * a listing, a shelter — it is the owner. Returning an empty collection is
     * a valid answer and means "nobody is told, and anybody may review it".
     *
     * @return Collection<int, User>
     */
    public function reviewSubjects(): Collection;
}
