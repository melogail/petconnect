<?php

namespace App\Nova\Actions;

use App\Actions\Comments\DeleteComment;
use App\Models\Comment;
use Illuminate\Support\Collection;
use Laravel\Nova\Actions\ActionResponse;
use Laravel\Nova\Actions\DestructiveAction;
use Laravel\Nova\Fields\ActionFields;
use Laravel\Nova\Fields\Field;
use Laravel\Nova\Http\Requests\NovaRequest;

/**
 * Remove a comment and everything hanging off it.
 *
 * A thin adapter onto App\Actions\Comments\DeleteComment, which is the only
 * supported way to delete a comment. A bare `$comment->delete()` lets
 * `comments.parent_id` cascade the whole subtree at the database level, firing
 * no Eloquent events, so the likes and reports on every descendant survive as
 * rows pointing at comments that no longer exist — items a moderator can
 * neither act on nor dismiss. The Action collects the subtree first and clears
 * those children inside one transaction.
 *
 * Nova's built-in delete is refused by App\Nova\Policies\CommentPolicy::delete
 * so this is the only route.
 */
class DeleteCommentThread extends DestructiveAction
{
    public function __construct(private readonly DeleteComment $deleteComment) {}

    /**
     * The displayable name of the action.
     *
     * @var \Stringable|string
     */
    public $name = 'Delete Comment (with replies)';

    /**
     * Perform the action on the given models.
     *
     * @param  Collection<int, Comment>  $models
     */
    public function handle(ActionFields $fields, Collection $models): ActionResponse
    {
        $models->each(function (Comment $comment): void {
            $this->deleteComment->handle($comment);
        });

        return ActionResponse::message(sprintf(
            '%d comment thread(s) deleted, along with their replies, likes and reports.',
            $models->count(),
        ));
    }

    /**
     * Get the fields available on the action.
     *
     * @return array<int, Field>
     */
    public function fields(NovaRequest $request): array
    {
        return [];
    }
}
