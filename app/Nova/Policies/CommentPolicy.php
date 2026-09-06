<?php

namespace App\Nova\Policies;

use App\Models\Admin;
use App\Nova\Comment as CommentResource;

/**
 * Authorization for the Comment resource, on the `admin` guard.
 *
 * A comment is a member's words, published under their name. An admin who
 * could edit one would be able to change what somebody is recorded as having
 * said, silently, so `update` is false and App\Nova\Comment has no writable
 * field. `create` is false for the same reason in reverse.
 *
 * `delete` is false too, but for a different reason: not because removal is
 * wrong — removal is the whole point of moderation — but because Nova's
 * built-in delete is the wrong mechanism. `comments.parent_id` cascades, so
 * deleting a root comment takes its subtree at the database level without
 * firing an Eloquent event, leaving the likes and reports on every descendant
 * behind as rows whose target no longer exists. App\Nova\Actions\
 * DeleteCommentThread delegates to Actions\Comments\DeleteComment, which
 * collects the subtree and clears those children in one transaction, and
 * `runDestructiveAction` is what lets it past this refusal.
 *
 * This is the Nova-side expression of moderation. App\Policies\CommentPolicy
 * is untouched and still answers only for members.
 */
class CommentPolicy
{
    /**
     * Determine whether the admin can list comments.
     */
    public function viewAny(Admin $admin): bool
    {
        return true;
    }

    /**
     * Determine whether the admin can view a comment.
     */
    public function view(Admin $admin, CommentResource $resource): bool
    {
        return true;
    }

    /**
     * Determine whether the admin can write a comment.
     */
    public function create(Admin $admin): bool
    {
        return false;
    }

    /**
     * Determine whether the admin can edit a comment.
     */
    public function update(Admin $admin, CommentResource $resource): bool
    {
        return false;
    }

    /**
     * Determine whether the admin can delete a comment with Nova's built-in
     * delete.
     *
     * No — see the class docblock. Only App\Nova\Actions\DeleteCommentThread.
     */
    public function delete(Admin $admin, CommentResource $resource): bool
    {
        return false;
    }

    /**
     * Determine whether the admin may run a destructive action.
     *
     * True, which is what lets DeleteCommentThread past the `delete` refusal.
     */
    public function runDestructiveAction(Admin $admin, CommentResource $resource): bool
    {
        return true;
    }

    /**
     * Determine whether the admin can restore a comment.
     *
     * `comments` does not soft delete; deletion is permanent by design, and a
     * comment's moderation trail is the report, which goes with it.
     */
    public function restore(Admin $admin, CommentResource $resource): bool
    {
        return false;
    }

    /**
     * Determine whether the admin can permanently delete a comment.
     */
    public function forceDelete(Admin $admin, CommentResource $resource): bool
    {
        return false;
    }
}
