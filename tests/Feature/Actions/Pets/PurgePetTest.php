<?php

use App\Actions\Pets\PurgePet;
use App\Models\Comment;
use App\Models\Like;
use App\Models\Pet;
use App\Models\Report;
use App\Models\Review;
use App\Models\Save;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

/**
 * Every polymorphic table in the schema with the pair of columns that points at
 * its target, scanned rather than spot checked for the same reason
 * tests/Feature/Actions/Profiles/DeleteUserAccountTest scans it: none of these
 * carries a foreign key, so nothing the database does on a hard delete reaches
 * them, and PurgePetContext's own docblock says a new polymorphic child is a
 * new step rather than a branch in an existing one.
 *
 * @var array<string, array{0: string, 1: string}>
 */
const LISTING_MORPH_TABLES = [
    'likes' => ['likeable_type', 'likeable_id'],
    'saves' => ['saveable_type', 'saveable_id'],
    'reports' => ['reportable_type', 'reportable_id'],
    'reviews' => ['reviewable_type', 'reviewable_id'],
    'comments' => ['commentable_type', 'commentable_id'],
    'notifications' => ['notifiable_type', 'notifiable_id'],
    'media' => ['model_type', 'model_id'],
];

/**
 * Every row in a polymorphic table whose target no longer exists, described as
 * `table -> alias#id` so a failure names what was stranded instead of saying a
 * count is not zero.
 *
 * Read through `withoutGlobalScopes()`: a retired listing is still a row, so a
 * comment on a soft-deleted pet is not an orphan.
 *
 * @return list<string>
 */
function strandedListingRows(): array
{
    $stranded = [];

    foreach (LISTING_MORPH_TABLES as $table => [$typeColumn, $idColumn]) {
        foreach (DB::table($table)->get() as $row) {
            /** @var class-string<Model>|null $class */
            $class = Relation::getMorphedModel($row->{$typeColumn});

            if ($class === null) {
                $stranded[] = "{$table} -> unmapped morph alias [{$row->{$typeColumn}}]";

                continue;
            }

            $exists = $class::query()
                ->withoutGlobalScopes()
                ->whereKey($row->{$idColumn})
                ->exists();

            if (! $exists) {
                $stranded[] = "{$table} -> {$row->{$typeColumn}}#{$row->{$idColumn}}";
            }
        }
    }

    return $stranded;
}

/**
 * One listing carrying every child a purge has to reach, and a second listing
 * of the same shape belonging to the same owner so a test can tell "cleared the
 * listing" from "emptied the tables".
 *
 * The thread is three levels deep on purpose: `comments.parent_id` cascades in
 * the database and fires no Eloquent event, so a step that only collected roots
 * and replies would strand the reports and likes on the third level while the
 * rows themselves silently vanished.
 *
 * The review is about the *owner*, not the listing — App\Enums\Reviewable has
 * only `user` — and it carries its own report, so both survive the purge and
 * the scan still reports zero.
 *
 * @return array{listing: Pet, bystander: Pet, owner: User, thread: list<Comment>}
 */
function listingWithEveryChild(): array
{
    $owner = User::factory()->create();
    $visitor = User::factory()->create();

    $listing = Pet::factory()->for($owner)->create();
    $bystander = Pet::factory()->for($owner)->create();

    $listing->addMedia(UploadedFile::fake()->image('cover.jpg'))
        ->withCustomProperties([Pet::FEATURED_PROPERTY => true])
        ->toMediaCollection(Pet::PHOTO_COLLECTION);
    $listing->addMedia(UploadedFile::fake()->image('gallery.jpg'))->toMediaCollection(Pet::PHOTO_COLLECTION);
    $bystander->addMedia(UploadedFile::fake()->image('theirs.jpg'))->toMediaCollection(Pet::PHOTO_COLLECTION);

    $root = Comment::factory()->for($visitor)->forPet($listing)->create();
    $reply = Comment::factory()->for($owner)->reply($root)->create();
    $deepReply = Comment::factory()->for($visitor)->reply($reply)->create();
    $bystanderComment = Comment::factory()->for($visitor)->forPet($bystander)->create();

    Like::factory()->for($visitor)->forPet($listing)->create();
    Like::factory()->for($owner)->forComment($root)->create();
    Like::factory()->for($visitor)->forComment($reply)->create();
    Like::factory()->for($owner)->forComment($deepReply)->create();
    Like::factory()->for($visitor)->forPet($bystander)->create();
    Like::factory()->for($owner)->forComment($bystanderComment)->create();

    Save::factory()->for($visitor)->forPet($listing)->create();
    Save::factory()->for($visitor)->forPet($bystander)->create();

    Report::factory()->for($owner)->forReportable($root)->create();
    Report::factory()->for($visitor)->forReportable($reply)->create();
    Report::factory()->for($owner)->forReportable($deepReply)->create();
    Report::factory()->for($owner)->forReportable($bystanderComment)->create();

    $review = Review::factory()->for($visitor)->forUser($owner)->create();
    Report::factory()->for($owner)->forReportable($review)->create();

    return [
        'listing' => $listing,
        'bystander' => $bystander,
        'owner' => $owner,
        'thread' => [$root, $reply, $deepReply],
    ];
}

beforeEach(function () {
    Storage::fake(config('media-library.disk_name'));
});

/**
 * The measurement this flow exists for. `pets.id` is reached by comments,
 * likes and saves through morph columns that carry no foreign key, and by the
 * reports on those comments at one remove, so a hard delete leaves every one of
 * them pointing at nothing.
 */
test('leaves no row in any morph table pointing at the purged listing', function () {
    ['listing' => $listing] = listingWithEveryChild();

    expect(strandedListingRows())->toBe([]);

    $purged = app(PurgePet::class)->handle($listing);

    expect($purged)->toBeTrue()
        ->and(strandedListingRows())->toBe([]);
    $this->assertModelMissing($listing);
});

test('removes the comment subtree with every like and report on it', function () {
    ['listing' => $listing, 'thread' => $thread] = listingWithEveryChild();

    $commentIds = collect($thread)->map->getKey()->all();

    app(PurgePet::class)->handle($listing);

    expect(Comment::query()->whereKey($commentIds)->count())->toBe(0)
        ->and(Report::query()
            ->where('reportable_type', Relation::getMorphAlias(Comment::class))
            ->whereIn('reportable_id', $commentIds)
            ->count())->toBe(0)
        ->and(Like::query()
            ->where('likeable_type', Relation::getMorphAlias(Comment::class))
            ->whereIn('likeable_id', $commentIds)
            ->count())->toBe(0)
        ->and(Like::query()
            ->where('likeable_type', Relation::getMorphAlias(Pet::class))
            ->where('likeable_id', $listing->getKey())
            ->count())->toBe(0)
        ->and(Save::query()
            ->where('saveable_type', Relation::getMorphAlias(Pet::class))
            ->where('saveable_id', $listing->getKey())
            ->count())->toBe(0);
});

/**
 * PurgeListingRecord force deletes through Eloquent precisely so medialibrary's
 * `deleting` hook runs. A bulk `whereKey()->forceDelete()` is one statement and
 * fires no model events: the rows would go and the bytes would stay on disk
 * with nothing left naming them, which nothing else in the suite would notice.
 */
test('removes the stored photo files of the purged listing and no others', function () {
    ['listing' => $listing, 'bystander' => $bystander] = listingWithEveryChild();

    $disk = Storage::disk(config('media-library.disk_name'));
    $purgedPaths = $listing->getMedia(Pet::PHOTO_COLLECTION)
        ->map(fn (Media $photo): string => $photo->getPathRelativeToRoot())
        ->all();
    $keptPath = $bystander->getFirstMedia(Pet::PHOTO_COLLECTION)->getPathRelativeToRoot();

    expect($purgedPaths)->toHaveCount(2);

    foreach ([...$purgedPaths, $keptPath] as $path) {
        $disk->assertExists($path);
    }

    app(PurgePet::class)->handle($listing);

    foreach ($purgedPaths as $path) {
        $disk->assertMissing($path);
    }

    $disk->assertExists($keptPath);
    expect(Media::query()->where('model_id', $listing->getKey())
        ->where('model_type', Relation::getMorphAlias(Pet::class))
        ->count())->toBe(0);
});

test('keeps the other listing of the same owner with all of its content', function () {
    ['listing' => $listing, 'bystander' => $bystander, 'owner' => $owner] = listingWithEveryChild();

    $bystanderCommentIds = Comment::query()
        ->where('commentable_type', Relation::getMorphAlias(Pet::class))
        ->where('commentable_id', $bystander->getKey())
        ->pluck('id');

    app(PurgePet::class)->handle($listing);

    expect(Comment::query()->whereKey($bystanderCommentIds)->count())->toBe($bystanderCommentIds->count())
        ->and($bystanderCommentIds)->not->toBeEmpty()
        ->and(Save::query()->where('saveable_id', $bystander->getKey())->count())->toBe(1)
        ->and(Like::query()->where('likeable_type', Relation::getMorphAlias(Pet::class))->count())->toBe(1)
        ->and(Review::query()->where('reviewable_id', $owner->getKey())->count())->toBe(1);
    $this->assertModelExists($bystander);
    $this->assertModelExists($owner);
});

/**
 * `pets` soft deletes, so the listing this is reached for is usually one an
 * admin already retired — DeleteCategory counts `withTrashed()` and refuses the
 * category until they are gone for good. A plain `delete()` in PurgeListingRecord
 * would only re-stamp `deleted_at`, clear the thread and leave the row and its
 * files behind.
 */
test('purges a listing that was already retired', function () {
    ['listing' => $listing] = listingWithEveryChild();
    $listing->delete();

    $purged = app(PurgePet::class)->handle($listing);

    expect($purged)->toBeTrue()
        ->and(Pet::withTrashed()->whereKey($listing->getKey())->exists())->toBeFalse()
        ->and(strandedListingRows())->toBe([]);
});
