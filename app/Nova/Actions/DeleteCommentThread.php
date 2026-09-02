<?php

namespace App\Nova\Actions;

use App\Actions\Comments\DeleteComment;
use App\Models\Comment;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Laravel\Nova\Actions\ActionResponse;
use Laravel\Nova\Actions\DestructiveAction;
use Laravel\Nova\Fields\ActionFields;
use Laravel\Nova\Fields\Field;
use Laravel\Nova\Http\Requests\NovaRequest;
use Throwable;

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
 *
 * ## The selection is one transaction, and a failure is a sentence
 *
 * The Action's own transaction covers one comment subtree. Nova hands this
 * handle() the **whole selection**, and a bare `$models->each(...)` would leave
 * a throw on the third of five as two threads deleted, three intact and an
 * unreadable 500 for the admin — the exact failure .ai/rules/nova-policies.md
 * records as already fixed once, on DeleteUserAccount. Same shape here:
 * DB::transaction around the selection, `catch (Throwable)` returning
 * ActionResponse::danger() so the admin is told nothing happened rather than
 * being left guessing which half did.
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
        try {
            DB::transaction(function () use ($models): void {
                $models->each(function (Comment $comment): void {
                    $this->deleteComment->handle($comment);
                });
            });
        } catch (Throwable $exception) {
            report($exception);

            return ActionResponse::danger(
                'Nothing was deleted. One of the selected comment threads could not be removed, so the whole selection was rolled back. The failure has been logged.',
            );
        }

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
