<?php

namespace App\Pipelines\Profiles\DeleteAccount;

use App\Models\User;
use LogicException;

/**
 * Passable for permanently deleting a user account.
 *
 * ## The problem this flow exists to solve
 *
 * `users` has no SoftDeletes, so `$user->delete()` is a hard DELETE, and eight
 * tables cascade off it in the database: `pets`, `comments` (plus their whole
 * `parent_id` subtree), `reviews` the user wrote, `likes`, `saves` and
 * `reports` the user filed, `conversation_user`, and `messages` they sent.
 *
 * **A database cascade fires no Eloquent events.** So a bare `$user->delete()`
 * runs none of Actions\Reviews\DeleteReview, none of
 * Actions\Comments\DeleteComment, and none of medialibrary's `deleting` hook
 * for anything the cascade takes. And the rows those Actions exist to clean up
 * reach their parents through **morph columns, which carry no foreign key at
 * all**, so nothing in the schema removes them either.
 *
 * Measured before this flow existed: A reviews B, C reports that review, A's
 * account is deleted — the review row is gone and C's report survives in the
 * moderation queue with `reportable` resolving to null. The same shape strands,
 * silently:
 *
 * - every **review written about** the deleted user (`reviewable` is a morph
 *   column; `reviews.user_id` cascades, `reviews.reviewable_id` does not) and
 *   every report filed against those reviews;
 * - every **report and like** on the user's own comments — and on every
 *   descendant of those comments, which `comments.parent_id` cascades away
 *   without an event;
 * - every **like** on the user's profile itself (`User` implements Likeable);
 * - every **comment, like, save, review and report** attached to the user's
 *   listings, because `pets` cascades and a pet's children are all
 *   polymorphic;
 * - every **media row and file** belonging to those listings, because the
 *   cascade skips medialibrary's `deleting` hook;
 * - every **notification** addressed to the user (`notifiable` is a morph).
 *
 * ## What this flow does instead
 *
 * Collect every affected id first — while the rows still exist — then delete
 * each polymorphic child explicitly by id, then take the listings through
 * Eloquent so their files go with them, and only then delete the user and let
 * the remaining cascades run. The Action wraps the whole run in one
 * transaction. It is the shape .ai/rules/pipelines.md records for
 * DeleteCommentThread, applied to the widest cascade in the application.
 *
 * ## What it deliberately leaves alone
 *
 * - **Conversations.** `conversation_user` and the user's own `messages`
 *   cascade; the `conversations` row survives with the other participant still
 *   attached. That is correct rather than an oversight — the other side keeps
 *   their thread — and a conversation with no participants left is a cleanup
 *   question of its own, not a side effect of one account closing.
 * - **The user's own media.** DeleteAccountRecord deletes the user through
 *   Eloquent, so medialibrary's `deleting` hook removes the avatar and its
 *   conversions. Only *cascaded* models need the explicit treatment.
 * - **Reports the user filed.** `reports.user_id` cascades, and a report is a
 *   message about someone else's content whose subject still exists; the
 *   moderator loses the reporter, not the item.
 *
 * A new polymorphic child of `User` — or of anything that cascades from `User`
 * — is a new step in this flow, not a branch in an existing one.
 */
class DeleteAccountContext
{
    /**
     * Ids of the listings the account owns, including trashed ones.
     *
     * @var list<int>|null
     */
    protected ?array $petIds = null;

    /**
     * Ids of every comment that is about to disappear: the ones the account
     * wrote, the ones written on its listings, and every descendant of both.
     *
     * @var list<int>|null
     */
    protected ?array $commentIds = null;

    /**
     * Ids of every review that is about to disappear: written by the account
     * (cascade) and written about it (no foreign key).
     *
     * @var list<int>|null
     */
    protected ?array $reviewIds = null;

    /**
     * Whether the user row was removed, once DeleteAccountRecord has run.
     */
    protected bool $deleted = false;

    public function __construct(
        public readonly User $user,
    ) {}

    /**
     * @param  list<int>  $petIds
     * @param  list<int>  $commentIds
     * @param  list<int>  $reviewIds
     */
    public function setCollectedContent(array $petIds, array $commentIds, array $reviewIds): void
    {
        $this->petIds = $petIds;
        $this->commentIds = $commentIds;
        $this->reviewIds = $reviewIds;
    }

    /**
     * @return list<int>
     *
     * @throws LogicException When read before CollectAccountContent has run.
     */
    public function petIds(): array
    {
        return $this->petIds ?? throw $this->notCollected();
    }

    /**
     * @return list<int>
     *
     * @throws LogicException When read before CollectAccountContent has run.
     */
    public function commentIds(): array
    {
        return $this->commentIds ?? throw $this->notCollected();
    }

    /**
     * @return list<int>
     *
     * @throws LogicException When read before CollectAccountContent has run.
     */
    public function reviewIds(): array
    {
        return $this->reviewIds ?? throw $this->notCollected();
    }

    public function markDeleted(bool $deleted): void
    {
        $this->deleted = $deleted;
    }

    public function deleted(): bool
    {
        return $this->deleted;
    }

    protected function notCollected(): LogicException
    {
        return new LogicException(self::class.' has no collected content yet; CollectAccountContent must run first.');
    }
}
