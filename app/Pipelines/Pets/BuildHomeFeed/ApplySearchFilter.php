<?php

namespace App\Pipelines\Pets\BuildHomeFeed;

use Closure;
use Illuminate\Database\Eloquent\Builder;

/**
 * Narrow the feed to the header search box's text.
 *
 * One term, matched as a substring of the listing's name **or** of its
 * breed's or category's name in either language — the three things a visitor
 * types into a box labelled "Search for pets, breeds…". A litter has no name,
 * so it is still found through its breed and category. The match is a single
 * `LIKE` per column with `%` on both sides: a prefix match would miss "Golden
 * Retriever" when the visitor types "retriever", and the catalogue is small
 * enough that an index-friendly form buys nothing yet.
 *
 * `%`, `_` and the escape character are escaped in the term so a visitor's
 * input is text, never a pattern: a bare `%` would otherwise match every
 * listing. The escape character is `!`, declared with `ESCAPE`, because the
 * drivers disagree about the backslash: MySQL defaults to it but also treats
 * it as an escape *inside a string literal*, so `ESCAPE '\'` is an unterminated
 * string there (a 1064 the SQLite-backed suite never sees), while SQLite has
 * no default escape at all. `!` is an ordinary character to both parsers and
 * is in no listing, breed or category name, so a visitor's backslash is
 * matched as text like everything else.
 *
 * The taxonomy matches go through `whereHas()`, which the breed step and the
 * nearby step already pay for in the same query plan, rather than a join that
 * would duplicate rows and upset the paginator's count.
 */
class ApplySearchFilter
{
    /**
     * The `LIKE` escape character: one both MySQL and SQLite read literally.
     */
    private const ESCAPE = '!';

    public function handle(HomeFeedContext $context, Closure $next): mixed
    {
        if (! $context->filters->hasSearch()) {
            return $next($context);
        }

        $term    = str_replace(
            [self::ESCAPE, '%', '_'],
            [self::ESCAPE.self::ESCAPE, self::ESCAPE.'%', self::ESCAPE.'_'],
            (string) $context->filters->search,
        );
        $pattern = '%'.$term.'%';
        $like    = 'LIKE ? ESCAPE \''.self::ESCAPE.'\'';

        $context->query->where(fn (Builder $builder): Builder => $builder
            ->whereRaw("name {$like}", [$pattern])
            ->orWhereHas('breed', fn (Builder $breed): Builder => $breed
                ->whereRaw("name {$like}", [$pattern])
                ->orWhereRaw("name_ar {$like}", [$pattern]))
            ->orWhereHas('category', fn (Builder $category): Builder => $category
                ->whereRaw("name {$like}", [$pattern])
                ->orWhereRaw("name_ar {$like}", [$pattern])));

        return $next($context);
    }
}
