<?php

namespace App\Policies;

use App\Models\Comment;
use App\Models\User;

/**
 * Authorization for comments.
 *
 * Every comment route runs through this policy via $this->authorize() in
 * CommentController, including the public ones: a guest-readable thread is a
 * decision recorded here rather than the absence of a check.
 *
 * The legacy CommentPolicy was an empty class, but it extended an abstract
 * App\Policies\Policy that did decide, and decided materially what is written
 * out longhand below: `create` was `isVerified()`, `update` and `delete` were
 * `isVerified()` and author. Two things differ. The rules are spelled out here
 * per policy instead of inherited, so reading this file tells you the whole
 * answer for comments; and the shared base also took an `Admin|User` first
 * parameter and returned true for any Admin on `update`, `delete`, `restore`
 * and `forceDelete`, which put moderation on the same gate as the web app's own
 * authorization. That is not ported — see the note on the type hints below.
 *
 * Writing, editing and liking all require a verified email address, because
 * each of them either publishes public content or notifies another user.
 *
 * Whether the *target* may be commented on at all is a different question and
 * is not asked here — it depends on a model resolved from the URL, and it is
 * answered by Pipelines\Comments\PublishComment (a hidden or soft-deleted
 * target raises ModelNotFoundException, a target with no thread raises
 * CommentingNotSupported). This policy only ever decides about the acting user.
 *
 * The methods type hint User rather than Admin|User on purpose: Nova
 * authenticates App\Models\Admin on its own guard, so an Admin cannot be
 * authorised by this policy. The hint is a tripwire rather than a gate —
 * Gate::canBeCalledWithUser() short-circuits to true for any non-null user and
 * only reads the signature for guests, so an Admin reaching one of these raises
 * a TypeError rather than returning false — and the guard is what keeps them
 * apart. Nova authorization belongs on the Nova resource, which is also where
 * comment moderation by anyone other than the author belongs; see delete().
 */
class CommentPolicy
{
    /**
     * Reading a thread is public: the pet page a thread hangs off is public,
     * and a comment that only signed-in visitors could read would be invisible
     * on the page it was written for.
     */
    public function viewAny(?User $user): bool
    {
        return true;
    }

    public function view(?User $user, Comment $comment): bool
    {
        return true;
    }

    /**
     * Publishing is public content, so it needs a verified account.
     */
    public function create(User $user): bool
    {
        return $user->isVerified();
    }

    public function update(User $user, Comment $comment): bool
    {
        return $user->isVerified() && $user->getKey() === $comment->user_id;
    }

    /**
     * Only the author may delete a comment, and deleting it takes its replies
     * with it.
     *
     * Deliberately *not* extended to the owner of the commentable. Letting a
     * pet owner delete comments on their own listing is a real moderation need,
     * but it is moderation, and answering it here would mean loading the
     * comment's polymorphic target inside the policy — a query per comment on
     * a payload that renders dozens of them — to decide something a moderator
     * screen should decide. The escalation path a listing owner has today is
     * the report flow; owner-side moderation is Phase 3's to design.
     */
    public function delete(User $user, Comment $comment): bool
    {
        return $user->isVerified() && $user->getKey() === $comment->user_id;
    }

    /**
     * Liking notifies the author, so it is gated on a verified account, exactly
     * as PetPolicy::like is.
     */
    public function like(User $user, Comment $comment): bool
    {
        return $user->isVerified();
    }
}
