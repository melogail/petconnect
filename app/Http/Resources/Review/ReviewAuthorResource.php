<?php

namespace App\Http\Resources\Review;

use App\Http\Resources\User\UserSummaryResource;

/**
 * The author of a review.
 *
 * Extends App\Http\Resources\User\UserSummaryResource rather than repeating its
 * five keys, joining Pet\PetOwnerResource and Comment\CommentAuthorResource.
 * That class exists because those two were byte-identical copies and a third
 * would have been the point where a rename in one started shipping a different
 * user object per page; this vertical is that third, so it takes the shared
 * shape rather than adding to the count.
 *
 * The legacy ReviewResource is what makes the case: it embedded its own inline
 * `'user' => ['id', 'name', 'profile_image']` array, spelled differently from
 * every other user payload in that app, and resolved the avatar by loading the
 * whole media collection and filtering it in PHP on a custom property.
 *
 * The subclass survives so a review payload can name its author "the review
 * author" without importing a class called PetOwnerResource to say so, and so a
 * future author-only key has somewhere to go that does not widen every user
 * summary in the app.
 *
 * The eager-loading contract is the parent's: whoever loads the author must
 * load `user.media`, because the avatar is a getFirstMediaUrl() call.
 * Actions\Reviews\ListReviews and Pipelines\Reviews\SubmitReview\PersistReview
 * both do.
 */
class ReviewAuthorResource extends UserSummaryResource {}
