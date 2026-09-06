<?php

namespace App\Concerns;

use Illuminate\Contracts\Validation\ValidationRule;

/**
 * The rules the comment Form Requests share.
 *
 * Only `content` is shared — publishing also accepts `parent_id`, editing does
 * not — but it is the key both requests must keep identical to the one
 * CommentResource emits, and the length ceiling is the one thing about a
 * comment that is likely to be tuned. Holding it once means the store and the
 * update forms cannot disagree about how long a comment may be, which is
 * exactly how the legacy pair drifted from their own column.
 *
 * The ceiling comes from `petconnect.comments.max_length` and the column is
 * `text`, so the two agree by construction. The legacy requests hardcoded
 * `max:500` against a `varchar(255)`: a 300-character comment was either
 * truncated or rejected by the driver depending on strict mode, and the
 * validator said nothing either way.
 *
 * See .ai/rules/requests.md on resource↔Form-Request key parity — the person
 * most likely to break it is whoever renames a key in this trait.
 *
 * ## Why a paging size lives on a validation trait
 *
 * `threadPerPage()` is not a rule and never will be, but it is a number the
 * thread page has to be told and Actions\Comments\ListCommentThread has to page
 * by, and the whole point of these accessors is that a config key has one
 * spelling and one default. Putting it anywhere else would make the second
 * reader a second `config()` call, which is the drift .ai/rules/concerns.md
 * forbids. Actions\Fortify\CreateNewUser already takes ProfileValidationRules
 * for the same reason: nothing on this trait touches a Request, so it is safe
 * off a Form Request.
 */
trait CommentValidationRules
{
    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    protected function commentContentRules(): array
    {
        return [
            'content' => ['required', 'string', 'max:'.$this->maxContentLength()],
        ];
    }

    /**
     * The bounds a page that renders the comment thread has to be told.
     *
     * Same arrangement as ReviewValidationRules::reviewBounds() and
     * MessageValidationRules::messageBounds(): built from the accessors the
     * `max:` rule and the paginator are built from, so the counter under the
     * box cannot disagree with the validator and the client's page cursor
     * cannot disagree with the endpoint. Shipped as the `commentBounds` prop by
     * Web\PetController::show, which is the page that hosts the thread.
     *
     * `max_length` bounds the composer. `thread_per_page` is the page size
     * `comments.index` answers with, and the client needs it because it cannot
     * infer it: the first slice of roots rides the page payload and is sized by
     * `petconnect.pets.detail_comment_page_size`, while the endpoint pages by
     * `petconnect.comments.thread_per_page`, and the two are independent env
     * vars. Without it the thread assumed the slice it already held was page
     * one and asked for page two's worth of duplicates on the first "load
     * more", which deduplicated to nothing and needed a second click before a
     * comment appeared. With it the first page to ask for is
     * `floor(rootsInHand / thread_per_page) + 1`.
     *
     * Snake_case keys matching the config, following `reviewBounds` and
     * `photoBounds`.
     *
     * @return array{max_length: int, thread_per_page: int}
     */
    public function commentBounds(): array
    {
        return [
            'max_length' => $this->maxContentLength(),
            'thread_per_page' => $this->threadPerPage(),
        ];
    }

    /**
     * The longest comment the application accepts, in characters.
     */
    public function maxContentLength(): int
    {
        return (int) config('petconnect.comments.max_length', 1000);
    }

    /**
     * How many top-level comments one page of `comments.index` carries.
     */
    public function threadPerPage(): int
    {
        return (int) config('petconnect.comments.thread_per_page', 20);
    }
}
