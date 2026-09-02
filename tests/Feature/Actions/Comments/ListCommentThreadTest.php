<?php

use App\Actions\Comments\ListCommentThread;
use App\Enums\Commentable;
use App\Http\Resources\Comment\CommentResource;
use App\MediaLibrary\MediaPathGenerator;
use App\Models\Comment;
use App\Models\Like;
use App\Models\Pet;
use App\Models\Report;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

/**
 * What a page of a thread costs: the target, the paginator's count, the
 * comments, their authors and those authors' avatars, then the same three for
 * the previewed replies.
 *
 * The cost is measured rather than guessed, and the test beside it grows the
 * thread instead of trusting the number alone — an eager load that stops
 * covering what CommentResource walks turns into a query per rendered comment,
 * which only a growing fixture makes visible.
 *
 * Asserted as an equality, not a ceiling: under a ceiling a regression of one
 * query passes silently until it happens to cross the bound, by which point the
 * commit that spent it is long gone.
 */
const THREAD_PAYLOAD_QUERY_COST = 8;

/**
 * Give a user the avatar CommentAuthorResource reads with getFirstMediaUrl().
 *
 * The owner directory is stamped on the media row exactly as the upload
 * pipeline does, so MediaPathGenerator never falls back to looking the owner
 * up — that fallback is a query of its own and would be counted below as if it
 * were a missing eager load.
 */
function attachThreadAvatar(User $user): void
{
    $user->addMedia(UploadedFile::fake()->image('avatar.jpg'))
        ->withCustomProperties([MediaPathGenerator::OWNER_DIRECTORY_PROPERTY => $user->media_directory_name])
        ->toMediaCollection('users');
}

/**
 * Hang top-level comments on a listing, each written by an author of its own
 * carrying an avatar, and each answered by further authors.
 */
function seedThreadOnListing(Pet $pet, int $comments, int $repliesEach): void
{
    for ($comment = 0; $comment < $comments; $comment++) {
        $author = User::factory()->create();
        attachThreadAvatar($author);

        $parent = Comment::factory()->for($author)->for($pet, 'commentable')->create();

        for ($reply = 0; $reply < $repliesEach; $reply++) {
            $replier = User::factory()->create();
            attachThreadAvatar($replier);

            Comment::factory()->for($replier)->reply($parent)->create();
        }
    }
}

/**
 * Serialise a page of the thread and report how many queries it took.
 *
 * The payload goes all the way to JSON on purpose: a resource only walks its
 * nested resources when something encodes it, so stopping at toArray() would
 * leave every author avatar and every previewed reply unresolved and the count
 * blind to what they cost.
 */
function countThreadQueries(Pet $pet, ?User $viewer): int
{
    DB::flushQueryLog();
    DB::enableQueryLog();

    CommentResource::collection(app(ListCommentThread::class)->handle(
        commentableType: Commentable::Pet,
        commentableId: $pet->getKey(),
        viewer: $viewer,
    ))->response()->getContent();

    $queries = count(DB::getQueryLog());
    DB::disableQueryLog();

    return $queries;
}

test('returns the top level comments only, each carrying a bounded preview of its replies and the true count of them', function () {
    $pet = Pet::factory()->create();
    $root = Comment::factory()->for($pet, 'commentable')->create();
    Comment::factory()->count(5)->reply($root)->create();
    $otherRoot = Comment::factory()->for($pet, 'commentable')->create();

    $page = app(ListCommentThread::class)->handle(
        commentableType: Commentable::Pet,
        commentableId: $pet->getKey(),
        replyPreview: 3,
    );

    expect($page->pluck('id')->all())->toEqualCanonicalizing([$root->getKey(), $otherRoot->getKey()])
        ->and($page->firstWhere('id', $root->getKey())->replies)->toHaveCount(3)
        ->and($page->firstWhere('id', $root->getKey())->replies_count)->toBe(5);
});

test('orders the thread newest first', function () {
    $pet = Pet::factory()->create();
    $older = Comment::factory()->for($pet, 'commentable')->create(['created_at' => now()->subHour()]);
    $newer = Comment::factory()->for($pet, 'commentable')->create(['created_at' => now()]);

    $page = app(ListCommentThread::class)->handle(
        commentableType: Commentable::Pet,
        commentableId: $pet->getKey(),
    );

    expect($page->pluck('id')->all())->toBe([$newer->getKey(), $older->getKey()]);
});

test('leaves out the comments of another listing', function () {
    $pet = Pet::factory()->create();
    $wanted = Comment::factory()->for($pet, 'commentable')->create();
    Comment::factory()->for(Pet::factory()->create(), 'commentable')->create();

    $page = app(ListCommentThread::class)->handle(
        commentableType: Commentable::Pet,
        commentableId: $pet->getKey(),
    );

    expect($page->pluck('id')->all())->toBe([$wanted->getKey()]);
});

test('flags the comments the viewer has liked and reported, and counts the likes of the rest', function () {
    $viewer = User::factory()->create();
    $pet = Pet::factory()->create();
    $liked = Comment::factory()->for($pet, 'commentable')->create();
    $untouched = Comment::factory()->for($pet, 'commentable')->create();
    Like::factory()->forComment($liked)->for($viewer)->create();
    Like::factory()->forComment($untouched)->create();
    Report::factory()->forReportable($liked)->for($viewer)->create();

    $page = app(ListCommentThread::class)->handle(
        commentableType: Commentable::Pet,
        commentableId: $pet->getKey(),
        viewer: $viewer,
    );

    $payload = collect(CommentResource::collection($page)->resolve())->keyBy('id');

    expect($payload[$liked->getKey()])
        ->is_liked->toBeTrue()
        ->has_reported->toBeTrue()
        ->likes_count->toBe(1);

    expect($payload[$untouched->getKey()])
        ->is_liked->toBeFalse()
        ->has_reported->toBeFalse()
        ->likes_count->toBe(1);
});

test('reports no like or report of its own for a guest', function () {
    $pet = Pet::factory()->create();
    $comment = Comment::factory()->for($pet, 'commentable')->create();
    Like::factory()->forComment($comment)->create();

    $page = app(ListCommentThread::class)->handle(
        commentableType: Commentable::Pet,
        commentableId: $pet->getKey(),
    );

    expect(CommentResource::collection($page)->resolve()[0])
        ->is_liked->toBeFalse()
        ->has_reported->toBeFalse()
        ->likes_count->toBe(1);
});

test('serialises a page of the thread in a constant number of queries however many comments it holds', function () {
    Storage::fake(config('media-library.disk_name'));
    $viewer = User::factory()->create();
    $pet = Pet::factory()->create();

    seedThreadOnListing($pet, comments: 3, repliesEach: 2);

    $atNineComments = countThreadQueries($pet, $viewer);

    seedThreadOnListing($pet, comments: 7, repliesEach: 2);

    $atThirtyComments = countThreadQueries($pet, $viewer);

    expect($atNineComments)->toBe($atThirtyComments)
        ->and($atThirtyComments)->toBe(THREAD_PAYLOAD_QUERY_COST);
});
