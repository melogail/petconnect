<?php

use App\Actions\Profiles\DeleteUserAccount;
use App\Models\Comment;
use App\Models\Conversation;
use App\Models\Like;
use App\Models\Message;
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
use Illuminate\Support\Str;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

/**
 * Every polymorphic table in the schema, with the pair of columns that points
 * at its target.
 *
 * None of these carries a foreign key, so the database cascade that removes a
 * user cannot reach them: a row here survives whatever happens to the thing it
 * describes. That is the whole class of damage DeleteUserAccount exists to
 * prevent, and the list is the scan rather than a spot check because the flow's
 * own docblock says a new polymorphic child is a new step.
 *
 * @var array<string, array{0: string, 1: string}>
 */
const MORPH_TABLES = [
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
 * `table -> alias#id` so a failure names what was stranded rather than saying
 * a count is not zero.
 *
 * Soft deletes are read through `withoutGlobalScopes()`: a trashed listing is
 * still a row, so a comment on it is not an orphan.
 *
 * @return list<string>
 */
function orphanedMorphRows(): array
{
    $orphans = [];

    foreach (MORPH_TABLES as $table => [$typeColumn, $idColumn]) {
        foreach (DB::table($table)->get() as $row) {
            /** @var class-string<Model>|null $class */
            $class = Relation::getMorphedModel($row->{$typeColumn});

            if ($class === null) {
                $orphans[] = "{$table} -> unmapped morph alias [{$row->{$typeColumn}}]";

                continue;
            }

            $exists = $class::query()
                ->withoutGlobalScopes()
                ->whereKey($row->{$idColumn})
                ->exists();

            if (! $exists) {
                $orphans[] = "{$table} -> {$row->{$typeColumn}}#{$row->{$idColumn}}";
            }
        }
    }

    return $orphans;
}

/**
 * @return array<string, int>
 */
function morphTableCounts(): array
{
    $counts = [];

    foreach (array_keys(MORPH_TABLES) as $table) {
        $counts[$table] = DB::table($table)->count();
    }

    return $counts;
}

/**
 * Send a notification row to a user without going through a notification class:
 * the payload's shape is NotificationResource's contract and is tested there;
 * here the row only has to exist so the delete can strand it.
 */
function notifyRow(User $user, string $type = 'like'): void
{
    $user->notifications()->create([
        'id' => (string) Str::uuid(),
        'type' => 'App\\Notifications\\ModelLikedNotification',
        'data' => ['type' => $type, 'message_key' => 'notifications.liked_pet'],
    ]);
}

/**
 * An account holding one of everything the cascade would strand, alongside a
 * second account holding the same shapes so the test can tell "cleaned up" from
 * "deleted the whole database".
 *
 * @return array{account: User, other: User, stranger: User}
 */
function richAccount(): array
{
    $account = User::factory()->create();
    $other = User::factory()->create();
    $stranger = User::factory()->create();

    $listing = Pet::factory()->for($account)->create();
    $retiredListing = Pet::factory()->for($account)->create();
    $othersListing = Pet::factory()->for($other)->create();

    $listing->addMedia(UploadedFile::fake()->image('cover.jpg'))->toMediaCollection(Pet::PHOTO_COLLECTION);
    $retiredListing->addMedia(UploadedFile::fake()->image('retired.jpg'))->toMediaCollection(Pet::PHOTO_COLLECTION);
    $othersListing->addMedia(UploadedFile::fake()->image('theirs.jpg'))->toMediaCollection(Pet::PHOTO_COLLECTION);
    $account->addMedia(UploadedFile::fake()->image('avatar.jpg'))->toMediaCollection('users');
    $other->addMedia(UploadedFile::fake()->image('their-avatar.jpg'))->toMediaCollection('users');

    $retiredListing->delete();

    $commentOnListing = Comment::factory()->for($other)->forPet($listing)->create();
    $replyOnListing = Comment::factory()->for($stranger)->reply($commentOnListing)->create();
    $ownComment = Comment::factory()->for($account)->forPet($othersListing)->create();
    $replyToOwnComment = Comment::factory()->for($other)->reply($ownComment)->create();
    $othersComment = Comment::factory()->for($stranger)->forPet($othersListing)->create();

    Like::factory()->for($other)->forUser($account)->create();
    Like::factory()->for($other)->forPet($listing)->create();
    Like::factory()->for($stranger)->forPet($retiredListing)->create();
    Like::factory()->for($stranger)->forComment($ownComment)->create();
    Like::factory()->for($stranger)->forComment($replyToOwnComment)->create();
    Like::factory()->for($stranger)->forComment($commentOnListing)->create();
    Like::factory()->for($account)->forPet($othersListing)->create();
    Like::factory()->for($stranger)->forUser($other)->create();
    Like::factory()->for($stranger)->forComment($othersComment)->create();

    Save::factory()->for($other)->forPet($listing)->create();
    Save::factory()->for($account)->forPet($othersListing)->create();
    Save::factory()->for($stranger)->forPet($othersListing)->create();

    $reviewAboutAccount = Review::factory()->for($other)->forUser($account)->create();
    $reviewByAccount = Review::factory()->for($account)->forUser($other)->create();
    $reviewAboutOther = Review::factory()->for($stranger)->forUser($other)->create();

    Report::factory()->for($stranger)->forReportable($reviewAboutAccount)->create();
    Report::factory()->for($stranger)->forReportable($reviewByAccount)->create();
    Report::factory()->for($stranger)->forReportable($ownComment)->create();
    Report::factory()->for($stranger)->forReportable($replyToOwnComment)->create();
    Report::factory()->for($stranger)->forReportable($commentOnListing)->create();
    Report::factory()->for($account)->forReportable($othersComment)->create();
    Report::factory()->for($stranger)->forReportable($reviewAboutOther)->create();

    notifyRow($account);
    notifyRow($account, 'comment');
    notifyRow($other);
    notifyRow($stranger, 'review');

    $conversation = Conversation::factory()->direct()->withParticipants($account, $other)->create();
    Message::factory()->for($conversation)->from($account)->create();
    Message::factory()->for($conversation)->from($other)->pinned($account)->create();

    return ['account' => $account, 'other' => $other, 'stranger' => $stranger];
}

beforeEach(function () {
    Storage::fake(config('media-library.disk_name'));
});

/**
 * The measurement this flow exists for. A bare `$user->delete()` leaves the
 * database cascade to run without a single Eloquent event, so every row above
 * that reaches its target through a morph column survives pointing at nothing.
 */
test('leaves no orphaned polymorphic row in any morph table', function () {
    ['account' => $account] = richAccount();

    expect(orphanedMorphRows())->toBe([]);
    $before = morphTableCounts();

    app(DeleteUserAccount::class)->handle($account);

    expect(orphanedMorphRows())->toBe([])
        ->and(morphTableCounts())->not->toBe($before);
    $this->assertModelMissing($account);
});

test('keeps every row belonging to the accounts that were not deleted', function () {
    ['account' => $account, 'other' => $other, 'stranger' => $stranger] = richAccount();

    $petIds = Pet::withTrashed()->where('user_id', $other->getKey())->pluck('id');
    $commentIds = Comment::query()->where('user_id', $stranger->getKey())
        ->whereIn('commentable_id', Pet::query()->where('user_id', $other->getKey())->pluck('id'))
        ->pluck('id');
    $reviewIds = Review::query()->where('reviewable_id', $other->getKey())
        ->where('reviewable_type', Relation::getMorphAlias(User::class))
        ->where('user_id', $stranger->getKey())->pluck('id');
    $notificationIds = $other->notifications()->pluck('id');

    app(DeleteUserAccount::class)->handle($account);

    expect(Pet::withTrashed()->whereKey($petIds)->count())->toBe($petIds->count())
        ->and(Comment::query()->whereKey($commentIds)->count())->toBe($commentIds->count())
        ->and(Review::query()->whereKey($reviewIds)->count())->toBe($reviewIds->count())
        ->and($other->notifications()->whereKey($notificationIds)->count())->toBe($notificationIds->count())
        ->and($notificationIds)->not->toBeEmpty();
    $this->assertModelExists($other);
    $this->assertModelExists($stranger);
});

/**
 * `reports.reportable_id` is a morph column, so C's report against A's review
 * of B survived A's deletion and sat in the moderation queue with `reportable`
 * resolving to null. Named on its own because it is the shape that was
 * measured before the flow existed.
 */
test('removes a report filed against a review the delete destroys', function () {
    $account = User::factory()->create();
    $subject = User::factory()->create();
    $moderatorReporter = User::factory()->create();
    $review = Review::factory()->for($account)->forUser($subject)->create();
    $report = Report::factory()->for($moderatorReporter)->forReportable($review)->create();

    app(DeleteUserAccount::class)->handle($account);

    $this->assertModelMissing($report);
    $this->assertModelMissing($review);
});

test('removes the reviews written about the account, which no foreign key covers', function () {
    $account = User::factory()->create();
    $reviewAbout = Review::factory()->forUser($account)->create();

    app(DeleteUserAccount::class)->handle($account);

    $this->assertModelMissing($reviewAbout);
});

/**
 * `PurgeOwnedListings` force deletes through Eloquent precisely so
 * medialibrary's `deleting` hook runs. A bulk `whereIn()->delete()` would drop
 * the rows and leave the bytes on the disk with nothing left that names them —
 * which no seeder and no other test would notice, because nothing else attaches
 * media.
 */
test('removes the stored files of the account and of its listings, trashed ones included', function () {
    $account = User::factory()->create();
    $listing = Pet::factory()->for($account)->create();
    $retired = Pet::factory()->for($account)->create();

    $avatar = $account->addMedia(UploadedFile::fake()->image('avatar.jpg'))->toMediaCollection('users');
    $cover = $listing->addMedia(UploadedFile::fake()->image('cover.jpg'))->toMediaCollection(Pet::PHOTO_COLLECTION);
    $retiredCover = $retired->addMedia(UploadedFile::fake()->image('retired.jpg'))->toMediaCollection(Pet::PHOTO_COLLECTION);
    $retired->delete();

    $disk = Storage::disk(config('media-library.disk_name'));
    $paths = [
        $avatar->getPathRelativeToRoot(),
        $cover->getPathRelativeToRoot(),
        $retiredCover->getPathRelativeToRoot(),
    ];

    foreach ($paths as $path) {
        $disk->assertExists($path);
    }

    app(DeleteUserAccount::class)->handle($account);

    foreach ($paths as $path) {
        $disk->assertMissing($path);
    }

    expect(Media::query()->count())->toBe(0);
});

describe('what it deliberately leaves alone', function () {
    /**
     * `conversation_user` and the account's own messages cascade; the
     * conversation itself survives with the other participant still attached,
     * so their thread does not vanish out from under them.
     */
    test('keeps the conversation and the other participant messages', function () {
        $account = User::factory()->create();
        $other = User::factory()->create();
        $conversation = Conversation::factory()->direct()->withParticipants($account, $other)->create();
        $theirs = Message::factory()->for($conversation)->from($other)->create();
        $ours = Message::factory()->for($conversation)->from($account)->create();

        app(DeleteUserAccount::class)->handle($account);

        $this->assertModelExists($conversation);
        $this->assertModelExists($theirs);
        $this->assertModelMissing($ours);
        expect($conversation->fresh()->users->pluck('id')->all())->toBe([$other->getKey()]);
    });

    /**
     * `messages.pinned_by` is `nullOnDelete`, not a cascade: removing the pin
     * would silently edit a conversation two other people are still having.
     */
    test('clears the pinner of a pinned message without unpinning it', function () {
        $account = User::factory()->create();
        $other = User::factory()->create();
        $conversation = Conversation::factory()->direct()->withParticipants($account, $other)->create();
        $pinned = Message::factory()->for($conversation)->from($other)->pinned($account)->create();

        app(DeleteUserAccount::class)->handle($account);

        expect($pinned->fresh())
            ->pinned_by->toBeNull()
            ->is_pinned->toBeTrue();
    });
});
