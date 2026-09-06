<?php

use App\Models\Admin;
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
use Illuminate\Support\Str;

/**
 * Every polymorphic table in the schema with the pair of columns that points at
 * its target. None carries a foreign key, so the cascade that removes a `users`
 * row cannot reach any of them.
 *
 * Declared here rather than shared with
 * tests/Feature/Actions/Profiles/DeleteUserAccountTest.php: that file declares
 * its scan at file scope, which only exists once Pest has loaded it, so reusing
 * it would make this file pass in a full run and fatal when run on its own.
 *
 * @var array<string, array{0: string, 1: string}>
 */
const NOVA_MORPH_TABLES = [
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
 * `table -> alias#id` so a failure names what was stranded.
 *
 * Soft deletes are read through `withoutGlobalScopes()`: a retired listing is
 * still a row, so a comment on it is not an orphan.
 *
 * @return list<string>
 */
function strandedNovaMorphRows(): array
{
    $stranded = [];

    foreach (NOVA_MORPH_TABLES as $table => [$typeColumn, $idColumn]) {
        foreach (DB::table($table)->get() as $row) {
            /** @var class-string<Model>|null $class */
            $class = Relation::getMorphedModel($row->{$typeColumn});

            if ($class === null) {
                $stranded[] = "{$table} -> unmapped morph alias [{$row->{$typeColumn}}]";

                continue;
            }

            if (! $class::query()->withoutGlobalScopes()->whereKey($row->{$idColumn})->exists()) {
                $stranded[] = "{$table} -> {$row->{$typeColumn}}#{$row->{$idColumn}}";
            }
        }
    }

    return $stranded;
}

/**
 * A member holding one row in each of the seven morph tables, next to a second
 * member holding the same shapes so a test can tell "cleaned up" from "emptied
 * the database".
 *
 * @return array{member: User, bystander: User}
 */
function memberWithOneOfEverything(): array
{
    $member = User::factory()->create();
    $bystander = User::factory()->create();

    $listing = Pet::factory()->for($member)->create();
    $theirListing = Pet::factory()->for($bystander)->create();

    $listing->addMedia(UploadedFile::fake()->image('cover.jpg'))->toMediaCollection(Pet::PHOTO_COLLECTION);
    $member->addMedia(UploadedFile::fake()->image('avatar.jpg'))->toMediaCollection('users');
    $bystander->addMedia(UploadedFile::fake()->image('their-avatar.jpg'))->toMediaCollection('users');

    $comment = Comment::factory()->for($member)->forPet($theirListing)->create();
    $theirComment = Comment::factory()->for($bystander)->forPet($theirListing)->create();

    Like::factory()->for($bystander)->forComment($comment)->create();
    Like::factory()->for($bystander)->forComment($theirComment)->create();
    Save::factory()->for($member)->forPet($theirListing)->create();
    Save::factory()->for($bystander)->forPet($theirListing)->create();

    $review = Review::factory()->for($bystander)->forUser($member)->create();
    $theirReview = Review::factory()->for($member)->forUser($bystander)->create();

    Report::factory()->pending()->for($bystander)->forReportable($review)->create();
    Report::factory()->pending()->for($bystander)->forReportable($theirComment)->create();

    foreach ([$member, $bystander] as $notifiable) {
        $notifiable->notifications()->create([
            'id' => (string) Str::uuid(),
            'type' => 'App\\Notifications\\ModelLikedNotification',
            'data' => ['type' => 'like', 'message_key' => 'notifications.liked_pet'],
        ]);
    }

    return ['member' => $member, 'bystander' => $bystander];
}

beforeEach(function () {
    Storage::fake(config('media-library.disk_name'));
});

/**
 * The measurement the whole arrangement exists for.
 *
 * Nova's built-in delete is refused for this resource (see
 * tests/Feature/Nova/Policies/UserPolicyTest.php); this action is the only
 * route, and it must leave the database in the state a bare `$user->delete()`
 * would not. Asserted as an invariant over every morph table rather than as a
 * count, because the damage a cascade does is rows that survive pointing at
 * nothing, and the number of them depends on the fixture.
 */
test('deletes a member account through Nova and strands no polymorphic row', function () {
    ['member' => $member, 'bystander' => $bystander] = memberWithOneOfEverything();
    $admin = Admin::factory()->create();

    expect(strandedNovaMorphRows())->toBe([]);

    $this->actingAs($admin, 'admin')
        ->postJson('/nova-api/users/action?action=delete-account-with-all-content', [
            'resources' => [$member->getKey()],
        ])
        ->assertOk()
        ->assertJsonPath('message', '1 account(s) deleted, along with every listing, comment, review, reaction, report, notification and uploaded file belonging to them.');

    $this->assertModelMissing($member);
    $this->assertModelExists($bystander);
    expect(strandedNovaMorphRows())->toBe([]);
});

test('keeps the rows belonging to the accounts it was not aimed at', function () {
    ['member' => $member, 'bystander' => $bystander] = memberWithOneOfEverything();
    $admin = Admin::factory()->create();

    $theirListings = Pet::query()->where('user_id', $bystander->getKey())->pluck('id');
    $theirComments = Comment::query()->where('user_id', $bystander->getKey())->pluck('id');
    $theirNotifications = $bystander->notifications()->pluck('id');

    $this->actingAs($admin, 'admin')
        ->postJson('/nova-api/users/action?action=delete-account-with-all-content', [
            'resources' => [$member->getKey()],
        ])
        ->assertOk();

    expect(Pet::query()->whereKey($theirListings)->count())->toBe($theirListings->count())
        ->and($theirListings)->not->toBeEmpty()
        ->and(Comment::query()->whereKey($theirComments)->count())->toBe($theirComments->count())
        ->and($theirComments)->not->toBeEmpty()
        ->and($bystander->notifications()->whereKey($theirNotifications)->count())->toBe($theirNotifications->count())
        ->and($theirNotifications)->not->toBeEmpty();
});

test('deletes every selected account when several are run at once', function () {
    $admin = Admin::factory()->create();
    $members = User::factory()->count(3)->create();
    $bystander = User::factory()->create();

    $this->actingAs($admin, 'admin')
        ->postJson('/nova-api/users/action?action=delete-account-with-all-content', [
            'resources' => $members->modelKeys(),
        ])
        ->assertOk()
        ->assertJsonPath('message', '3 account(s) deleted, along with every listing, comment, review, reaction, report, notification and uploaded file belonging to them.');

    expect(User::query()->pluck('id')->all())->toBe([$bystander->getKey()]);
});
