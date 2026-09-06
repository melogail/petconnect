<?php

namespace App\Actions\Comments;

use App\Enums\Commentable;
use App\Models\Comment;
use App\Models\User;
use App\Pipelines\Comments\PublishComment\NotifyCommentable;
use App\Pipelines\Comments\PublishComment\PersistComment;
use App\Pipelines\Comments\PublishComment\PublishCommentContext;
use App\Pipelines\Comments\PublishComment\RequireCommentThread;
use App\Pipelines\Comments\PublishComment\ResolveCommentable;
use App\Pipelines\Comments\PublishComment\ValidateParentBelongsToCommentable;
use App\Pipelines\Comments\Shared\CleanContent;
use Illuminate\Pipeline\Pipeline;

/**
 * Publish a comment or a reply.
 *
 * A sequence — resolve the target, confirm it holds a thread, clean the text,
 * confirm the parent, write the row, notify — so it runs as a pipeline over a
 * typed context rather than as one long method.
 *
 * Order is load bearing in three places. Resolution comes first because
 * everything after it is a question about the resolved target. The parent check
 * runs after cleaning and before persisting, so a rejected reply leaves nothing
 * behind. Notification runs last, so nobody is told about a row that failed to
 * write.
 *
 * This Action is where the flow's tunables are resolved — the masked word list
 * and the mask itself — so no step reads config() and the whole flow can be
 * driven with an explicit list from a test or the console.
 *
 * Throttling is deliberately not a step here: it is a named limiter on the
 * route (`throttle:comments`, defined in AppServiceProvider). See the class
 * docblock note in CommentController.
 */
class CreateComment
{
    public function __construct(private readonly Pipeline $pipeline) {}

    public function handle(
        User $author,
        Commentable $commentableType,
        int $commentableId,
        string $content,
        ?int $parentId = null,
    ): Comment {
        $context = new PublishCommentContext(
            author: $author,
            commentableType: $commentableType,
            commentableId: $commentableId,
            content: $content,
            parentId: $parentId,
            bannedWords: $this->bannedWords(),
            mask: (string) config('bad-words.mask', '****'),
        );

        return $this->pipeline
            ->send($context)
            ->through([
                ResolveCommentable::class,
                RequireCommentThread::class,
                CleanContent::class,
                ValidateParentBelongsToCommentable::class,
                PersistComment::class,
                NotifyCommentable::class,
            ])
            ->then(fn (PublishCommentContext $completed): Comment => $completed->comment());
    }

    /**
     * @return list<string>
     */
    protected function bannedWords(): array
    {
        /** @var list<string> $words */
        $words = config('bad-words.words', []);

        return $words;
    }
}
