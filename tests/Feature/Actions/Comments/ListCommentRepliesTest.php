<?php

use App\Actions\Comments\ListCommentReplies;
use App\Http\Resources\Comment\CommentResource;
use App\MediaLibrary\MediaPathGenerator;
use App\Models\Comment;
use App\Models\Pet;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

/**
 * What a page of replies costs the Action: the paginator's count, the replies,
 * their authors and those authors' avatars.
 *
 * One fewer relation than the thread endpoint, because a reply carries no
 * replies of its own — threads are two levels deep — and no target is resolved
 * here, the parent comment having already been bound by the route.
 *
 * The endpoint costs more than this, and REPLIES_ROUTE_QUERY_COST is the
 * figure to read as "what the replies endpoint costs".
 *
 * Asserted as an equality, not a ceiling: under a ceiling a regression of one
 * query passes silently until it happens to cross the bound, by which point the
 * commit that spent it is long gone.
 */
const REPLIES_ACTION_QUERY_COST = 4;

/**
 * What the replies endpoint costs: the Action's queries plus the two the
 * binding adds — the `{comment}` lookup itself, and the `commentable` load
 * Comment::resolveRouteBinding() makes to decide whether the listing the
 * comment hangs off is still visible.
 *
 * That second query is the price of the visibility rule, and it is paid by
 * every route bound to `{comment}` — `comments.replies`, `comments.like`,
 * `comments.update`, `comments.destroy`. It is pinned here rather than left
 * implicit so that turning the rule into something cheaper, or accidentally
 * into something per-comment, shows up as a number rather than as nothing.
 */
const REPLIES_ROUTE_QUERY_COST = 6;

/**
 * Give a user the avatar CommentAuthorResource reads with getFirstMediaUrl().
 *
 * The owner directory is stamped on the media row as the upload pipeline does,
 * so MediaPathGenerator never falls back to looking the owner up — that
 * fallback is a query of its own and would be counted below as if it were a
 * missing eager load.
 */
function attachReplyAuthorAvatar(User $user): void
{
    $user->addMedia(UploadedFile::fake()->image('avatar.jpg'))
        ->withCustomProperties([MediaPathGenerator::OWNER_DIRECTORY_PROPERTY => $user->media_directory_name])
        ->toMediaCollection('users');
}

/**
 * Answer a comment with replies, each written by an author of its own carrying
 * the avatar CommentAuthorResource reads.
 */
function seedRepliesOnComment(Comment $parent, int $replies): void
{
    for ($reply = 0; $reply < $replies; $reply++) {
        $replier = User::factory()->create();
        attachReplyAuthorAvatar($replier);

        Comment::factory()->for($replier)->reply($parent)->create();
    }
}

/**
 * Serialise a page of replies and report how many queries it took.
 *
 * Encoded all the way to JSON, because a resource only walks its nested
 * resources when something encodes it: stopping at toArray() would leave every
 * author avatar unresolved and the count blind to what it costs.
 */
function countRepliesQueries(Comment $parent, ?User $viewer): int
{
    DB::flushQueryLog();
    DB::enableQueryLog();

    CommentResource::collection(app(ListCommentReplies::class)->handle(
        comment: $parent,
        viewer: $viewer,
    ))->response()->getContent();

    $queries = count(DB::getQueryLog());
    DB::disableQueryLog();

    return $queries;
}

/**
 * Request a page of replies over the route and report how many queries it took.
 *
 * The response is a JSON resource collection, so the request itself does the
 * encoding countRepliesQueries() has to ask for by hand.
 */
function countRepliesRouteQueries(Comment $parent, User $viewer): int
{
    test()->actingAs($viewer);

    DB::flushQueryLog();
    DB::enableQueryLog();

    test()->get(route('comments.replies', $parent))->assertOk();

    $queries = count(DB::getQueryLog());
    DB::disableQueryLog();

    return $queries;
}

test('returns the replies of the given comment newest first, and nothing else', function () {
    $pet = Pet::factory()->create();
    $parent = Comment::factory()->for($pet, 'commentable')->create();
    $older = Comment::factory()->reply($parent)->create(['created_at' => now()->subHour()]);
    $newer = Comment::factory()->reply($parent)->create(['created_at' => now()]);
    Comment::factory()->reply(Comment::factory()->for($pet, 'commentable')->create())->create();

    $page = app(ListCommentReplies::class)->handle($parent);

    expect($page->pluck('id')->all())->toBe([$newer->getKey(), $older->getKey()]);
});

test('pages the replies by the configured page size', function () {
    config(['petconnect.comments.replies_per_page' => 2]);
    $parent = Comment::factory()->create();
    Comment::factory()->count(3)->reply($parent)->create();

    $page = app(ListCommentReplies::class)->handle($parent);

    expect($page->total())->toBe(3)
        ->and($page->items())->toHaveCount(2);
});

test('serialises a page of replies in a constant number of queries however many replies it holds', function () {
    Storage::fake(config('media-library.disk_name'));
    $viewer = User::factory()->create();
    $parent = Comment::factory()->create();
    config(['petconnect.comments.replies_per_page' => 50]);

    seedRepliesOnComment($parent, replies: 2);

    $atTwoReplies = countRepliesQueries($parent, $viewer);

    seedRepliesOnComment($parent, replies: 10);

    $atTwelveReplies = countRepliesQueries($parent, $viewer);

    expect($atTwoReplies)->toBe($atTwelveReplies)
        ->and($atTwelveReplies)->toBe(REPLIES_ACTION_QUERY_COST);
});

test('serves a page of replies in a constant number of queries however many replies it holds', function () {
    Storage::fake(config('media-library.disk_name'));
    $viewer = User::factory()->create();
    $parent = Comment::factory()->create();
    config(['petconnect.comments.replies_per_page' => 50]);

    seedRepliesOnComment($parent, replies: 2);

    $atTwoReplies = countRepliesRouteQueries($parent, $viewer);

    seedRepliesOnComment($parent, replies: 10);

    $atTwelveReplies = countRepliesRouteQueries($parent, $viewer);

    expect($atTwoReplies)->toBe($atTwelveReplies)
        ->and($atTwelveReplies)->toBe(REPLIES_ROUTE_QUERY_COST);
});
