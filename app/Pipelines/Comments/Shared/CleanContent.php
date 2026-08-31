<?php

namespace App\Pipelines\Comments\Shared;

use App\Actions\Content\SanitizeContent;
use App\Pipelines\Comments\CommentContentContext;
use Closure;

/**
 * Trim the submitted text and mask the words the app will not publish.
 *
 * One step rather than the legacy pair of TrimContentPipeline and
 * FilterBadWordsPipeline: the two are never wanted apart, they only work in
 * that order, and the messaging vertical needs the pair as a unit rather than
 * two step classes imported out of the Comments namespace. The work itself
 * lives in App\Actions\Content\SanitizeContent, which is domain-free; this step
 * is its only caller today, and it stays a separate Action because the
 * messaging vertical will be the second one.
 *
 * This is the one step in the Comments domain that hints the abstract context,
 * because it is the one step both the publish and the revise flow run — the
 * same exemption Pipelines\Pets\Shared\* has (see .ai/rules/pipelines.md). It
 * only ever reads and rewrites `content`, so widening it to a flow it was not
 * written for cannot reach anything flow-specific.
 */
class CleanContent
{
    public function __construct(private readonly SanitizeContent $sanitizeContent) {}

    public function handle(CommentContentContext $context, Closure $next): mixed
    {
        $context->setContent($this->sanitizeContent->handle(
            content: $context->content(),
            bannedWords: $context->bannedWords,
            mask: $context->mask,
        ));

        return $next($context);
    }
}
