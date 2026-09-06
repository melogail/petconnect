<?php

use App\Models\Admin;
use App\Models\Breed;
use App\Models\Category;
use App\Models\Pet;

/**
 * The trap this action exists for.
 *
 * `pets.category_id` is `restrictOnDelete` and `pets` soft deletes, so a
 * retired listing keeps its row, keeps its `category_id` and still satisfies
 * the foreign key. The category then reads "0 listings" everywhere in the
 * application while the database still refuses to let it go — and a plain
 * delete surfaces as a QueryException, i.e. a 500 and a red toast with nothing
 * an admin can act on.
 *
 * The count is taken `withTrashed()` because that is the number the constraint
 * actually sees, and it is taken before anything is deleted, so a mixed
 * selection cannot half-succeed.
 */
test('refuses a category whose only listings are retired, without a driver error', function () {
    $admin = Admin::factory()->create();
    $category = Category::factory()->create(['name' => 'Reptiles']);
    $breed = Breed::factory()->for($category)->create();
    $retired = Pet::factory()->for($category)->create();
    $retired->delete();

    $this->actingAs($admin, 'admin')
        ->postJson('/nova-api/categories/action?action=delete-category', [
            'resources' => [$category->getKey()],
        ])
        ->assertOk()
        ->assertJsonPath('danger', 'Nothing was deleted. Reptiles (1 listing) still has listings attached — including soft-deleted ones, which keep their category and are what the database constraint sees. Move or permanently delete those listings first.');

    $this->assertModelExists($category);
    $this->assertModelExists($breed);
    $this->assertSoftDeleted($retired);
});

test('refuses a category with a live listing', function () {
    $admin = Admin::factory()->create();
    $category = Category::factory()->create(['name' => 'Birds']);
    $listing = Pet::factory()->for($category)->create();

    $this->actingAs($admin, 'admin')
        ->postJson('/nova-api/categories/action?action=delete-category', [
            'resources' => [$category->getKey()],
        ])
        ->assertOk()
        ->assertJsonPath('danger', 'Nothing was deleted. Birds (1 listing) still has listings attached — including soft-deleted ones, which keep their category and are what the database constraint sees. Move or permanently delete those listings first.');

    $this->assertModelExists($category);
    $this->assertModelExists($listing);
});

/**
 * Nothing partially succeeds: the run is refused as a whole, so the empty
 * category selected alongside a blocked one survives too.
 */
test('deletes nothing at all when one category of a selection is blocked', function () {
    $admin = Admin::factory()->create();
    $blocked = Category::factory()->create(['name' => 'Cats']);
    $empty = Category::factory()->create();
    Pet::factory()->for($blocked)->create();

    $this->actingAs($admin, 'admin')
        ->postJson('/nova-api/categories/action?action=delete-category', [
            'resources' => [$blocked->getKey(), $empty->getKey()],
        ])
        ->assertOk()
        ->assertJsonPath('danger', 'Nothing was deleted. Cats (1 listing) still has listings attached — including soft-deleted ones, which keep their category and are what the database constraint sees. Move or permanently delete those listings first.');

    $this->assertModelExists($blocked);
    $this->assertModelExists($empty);
});

/**
 * `breeds.category_id` is `cascadeOnDelete`, so a category's breeds go with it.
 * That is stated in the confirmation text rather than blocked, because a breed
 * has no meaning without its category.
 */
test('deletes an empty category along with its breeds', function () {
    $admin = Admin::factory()->create();
    $category = Category::factory()->create();
    $breed = Breed::factory()->for($category)->create();
    $survivor = Breed::factory()->create();

    $this->actingAs($admin, 'admin')
        ->postJson('/nova-api/categories/action?action=delete-category', [
            'resources' => [$category->getKey()],
        ])
        ->assertOk()
        ->assertJsonPath('message', '1 category deleted, along with its breeds.');

    $this->assertModelMissing($category);
    $this->assertModelMissing($breed);
    $this->assertModelExists($survivor);
});

test('deletes several empty categories at once', function () {
    $admin = Admin::factory()->create();
    $categories = Category::factory()->count(2)->create();
    $survivor = Category::factory()->create();

    $this->actingAs($admin, 'admin')
        ->postJson('/nova-api/categories/action?action=delete-category', [
            'resources' => $categories->modelKeys(),
        ])
        ->assertOk()
        ->assertJsonPath('message', '2 categories deleted, along with their breeds.');

    expect(Category::query()->pluck('id')->all())->toBe([$survivor->getKey()]);
});
