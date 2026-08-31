<?php

namespace App\Nova\Policies;

use App\Models\Admin;
use App\Nova\Review as ReviewResource;

/**
 * Authorization for the Review resource, on the `admin` guard.
 *
 * The same shape as CommentPolicy and for the same reasons: a review is one
 * member's stated opinion of another, so an admin may read it and remove it
 * but never rewrite it.
 *
 * Nova's built-in delete is off because `reports.reportable_id` is a morph
 * column with no foreign key — deleting a review leaves every report filed
 * against it in the queue with a target that resolves to null.
 * App\Nova\Actions\DeleteReview delegates to Actions\Reviews\DeleteReview,
 * which removes both in one transaction, and `runDestructiveAction` lets it
 * past this refusal.
 *
 * App\Policies\ReviewPolicy is untouched: its `delete` type-hints
 * App\Models\User and answers only for the review's own author.
 */
class ReviewPolicy
{
    /**
     * Determine whether the admin can list reviews.
     */
    public function viewAny(Admin $admin): bool
    {
        return true;
    }

    /**
     * Determine whether the admin can view a review.
     */
    public function view(Admin $admin, ReviewResource $resource): bool
    {
        return true;
    }

    /**
     * Determine whether the admin can write a review.
     */
    public function create(Admin $admin): bool
    {
        return false;
    }

    /**
     * Determine whether the admin can edit a review.
     */
    public function update(Admin $admin, ReviewResource $resource): bool
    {
        return false;
    }

    /**
     * Determine whether the admin can delete a review with Nova's built-in
     * delete.
     *
     * No — see the class docblock. Only App\Nova\Actions\DeleteReview.
     */
    public function delete(Admin $admin, ReviewResource $resource): bool
    {
        return false;
    }

    /**
     * Determine whether the admin may run a destructive action.
     *
     * True, which is what lets DeleteReview past the `delete` refusal.
     */
    public function runDestructiveAction(Admin $admin, ReviewResource $resource): bool
    {
        return true;
    }

    /**
     * Determine whether the admin can restore a review.
     *
     * `reviews` does not soft delete.
     */
    public function restore(Admin $admin, ReviewResource $resource): bool
    {
        return false;
    }

    /**
     * Determine whether the admin can permanently delete a review.
     */
    public function forceDelete(Admin $admin, ReviewResource $resource): bool
    {
        return false;
    }
}
