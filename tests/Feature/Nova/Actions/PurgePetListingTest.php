<?php

use App\Models\Admin;
use App\Models\Breed;
use App\Models\Category;
use App\Models\Comment;
use App\Models\Like;
use App\Models\Pet;
use App\Models\Report;
use App\Models\Save;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

const PURGE_LISTING_ACTION = '/nova-api/pets/action?action=permanently-delete-listing-with-all-content';

beforeEach(function () {
    Storage::fake(config('media-library.disk_name'));
});

/**
 * The dead end this action was added to open, end to end.
 *
 * `pets.category_id` is `restrictOnDelete` and `pets` soft deletes, so a retired
 * listing keeps its row, keeps its `category_id` and still satisfies the foreign
 * key. DeleteCategory counts `withTrashed()` for exactly that reason and tells
 * the admin to "move or permanently delete those listings first" — and until
 * this action existed the second half of that sentence named an operation the
 * back office did not have, so the category could not be removed by any route.
 */
test('lets a category be deleted once its retired listings are purged', function () {
    $admin = Admin::factory()->create();
    $category = Category::factory()->create(['name' => 'Reptiles']);
    $breed = Breed::factory()->for($category)->create();
    $retired = Pet::factory()->for($category)->create();
    $retired->delete();

    $this->actingAs($admin, 'admin')
        ->postJson('/nova-api/categories/action?action=delete-category', ['resources' => [$category->getKey()]])
        ->assertOk()
        ->assertJsonPath('danger', 'Nothing was deleted. Reptiles (1 listing) still has listings attached — including soft-deleted ones, which keep their category and are what the database constraint sees. Move or permanently delete those listings first.');

    $this->actingAs($admin, 'admin')
        ->postJson(PURGE_LISTING_ACTION, ['resources' => [$retired->getKey()]])
        ->assertOk()
        ->assertJsonPath('message', '1 listing permanently deleted, along with its comment thread, reactions, saves, reports and photos.');

    $this->actingAs($admin, 'admin')
        ->postJson('/nova-api/categories/action?action=delete-category', ['resources' => [$category->getKey()]])
        ->assertOk()
        ->assertJsonPath('message', '1 category deleted, along with its breeds.');

    expect(Pet::withTrashed()->whereKey($retired->getKey())->exists())->toBeFalse();
    $this->assertModelMissing($category);
    $this->assertModelMissing($breed);
});

test('permanently deletes a listing with its thread, reactions, saves, reports and photos', function () {
    $admin = Admin::factory()->create();
    $visitor = User::factory()->create();
    $listing = Pet::factory()->create();

    $photo = $listing->addMedia(UploadedFile::fake()->image('cover.jpg'))->toMediaCollection(Pet::PHOTO_COLLECTION);
    $path = $photo->getPathRelativeToRoot();
    $comment = Comment::factory()->for($visitor)->forPet($listing)->create();
    $reply = Comment::factory()->for($visitor)->reply($comment)->create();
    $like = Like::factory()->for($visitor)->forPet($listing)->create();
    $save = Save::factory()->for($visitor)->forPet($listing)->create();
    $report = Report::factory()->for($visitor)->forReportable($reply)->create();

    $this->actingAs($admin, 'admin')
        ->postJson(PURGE_LISTING_ACTION, ['resources' => [$listing->getKey()]])
        ->assertOk()
        ->assertJsonPath('message', '1 listing permanently deleted, along with its comment thread, reactions, saves, reports and photos.');

    $this->assertModelMissing($listing);
    $this->assertModelMissing($comment);
    $this->assertModelMissing($reply);
    $this->assertModelMissing($like);
    $this->assertModelMissing($save);
    $this->assertModelMissing($report);
    Storage::disk(config('media-library.disk_name'))->assertMissing($path);
});

test('permanently deletes several listings at once', function () {
    $admin = Admin::factory()->create();
    $listings = Pet::factory()->count(2)->create();
    $survivor = Pet::factory()->create();

    $this->actingAs($admin, 'admin')
        ->postJson(PURGE_LISTING_ACTION, ['resources' => $listings->modelKeys()])
        ->assertOk()
        ->assertJsonPath('message', '2 listings permanently deleted, along with their comment threads, reactions, saves, reports and photos.');

    expect(Pet::withTrashed()->pluck('id')->all())->toBe([$survivor->getKey()]);
});

/**
 * Nova's built-in delete stays the reversible one: `pets` soft deletes, and a
 * retired listing keeps its row, its photos and its thread for moderation.
 */
test('retires rather than destroys a listing deleted with the built-in delete', function () {
    $admin = Admin::factory()->create();
    $listing = Pet::factory()->create();
    $comment = Comment::factory()->forPet($listing)->create();

    $this->actingAs($admin, 'admin')
        ->deleteJson('/nova-api/pets', ['resources' => [$listing->getKey()]])
        ->assertOk();

    $this->assertSoftDeleted($listing);
    $this->assertModelExists($comment);
});

/**
 * PetPolicy::forceDelete is false so that Nova's own force delete — which is
 * `$model->forceDelete()` and nothing else — cannot become a second, unguarded
 * route to the same outcome, stranding every morph row the action clears.
 */
test('removes nothing when the built-in force delete is aimed at a retired listing', function () {
    $admin = Admin::factory()->create();
    $listing = Pet::factory()->create();
    $listing->delete();

    $this->actingAs($admin, 'admin')
        ->deleteJson('/nova-api/pets/force', ['resources' => [$listing->getKey()]])
        ->assertOk();

    expect(Pet::withTrashed()->whereKey($listing->getKey())->exists())->toBeTrue();
});
