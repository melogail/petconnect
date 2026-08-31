<?php

namespace App\Pipelines\Messages\Shared;

use App\Actions\Content\SanitizeContent;
use App\Pipelines\Messages\MessageContentContext;
use Closure;

/**
 * Trim the submitted text and mask the words the app will not publish.
 *
 * The work lives in App\Actions\Content\SanitizeContent, which was put outside
 * app/Actions/Comments precisely so this vertical could call it rather than
 * grow a second copy of the list. It is the Action's second caller, which is
 * what .ai/rules/pipelines.md asks for before an Action is extracted at all —
 * the comments step's docblock predicted this one.
 *
 * This is the one step in the Messages domain that hints the abstract context,
 * because it is the one step both the send and the revise flow run — the same
 * exemption Pipelines\Pets\Shared\* and Pipelines\Comments\Shared\CleanContent
 * have. It only ever reads and rewrites `content`, so widening it to a flow it
 * was not written for cannot reach anything flow-specific.
 *
 * Running it on the edit path as well as the send path is the point: a message
 * cannot be rewritten around the filter it was sent through.
 *
 * The class name matches Pipelines\Comments\Shared\CleanContent and the two are
 * unrelated types on purpose — see MessageContentContext for why messaging owns
 * its own context rather than borrowing the comments one.
 */
class CleanContent
{
    public function __construct(private readonly SanitizeContent $sanitizeContent) {}

    public function handle(MessageContentContext $context, Closure $next): mixed
    {
        $context->setContent($this->sanitizeContent->handle(
            content: $context->content(),
            bannedWords: $context->bannedWords,
            mask: $context->mask,
        ));

        return $next($context);
    }
}
