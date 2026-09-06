<?php

use App\Models\Admin;
use App\Models\Category;
use App\Models\Pet;
use Illuminate\Support\Facades\DB;

/**
 * Deleting a category is refused by the policy for a database reason rather
 * than a permission one: `pets.category_id` is `restrictOnDelete` while `pets`
 * soft deletes, so a retired listing keeps its row, keeps its `category_id` and
 * still satisfies the foreign key. Nova's built-in delete would hand that to
 * the driver and turn a category that reads "0 listings" into a 500 with
 * "FOREIGN KEY constraint failed" and no explanation.
 *
 * App\Nova\Actions\DeleteCategory is the only route, and it counts
 * `withTrashed()` before it deletes anything — see its own test for the
 * refusal message and the clean delete.
 */
test('removes nothing when the built-in delete is aimed at a category', function () {
    $admin = Admin::factory()->create();
    $category = Category::factory()->create();
    $retired = Pet::factory()->for($category)->create();
    $retired->delete();

    $this->actingAs($admin, 'admin')
        ->deleteJson('/nova-api/categories', ['resources' => [$category->getKey()]])
        ->assertOk();

    $this->assertModelExists($category);
});

test('removes nothing when the built-in delete is aimed at an empty category', function () {
    $admin = Admin::factory()->create();
    $category = Category::factory()->create();

    $this->actingAs($admin, 'admin')
        ->deleteJson('/nova-api/categories', ['resources' => [$category->getKey()]])
        ->assertOk();

    $this->assertModelExists($category);
});

/**
 * Three categories rather than one, and no `Model::preventLazyLoading(false)`:
 * see the note in tests/Feature/Nova/Policies/UserPolicyTest.php for what that
 * suppression was hiding and why the row count is part of the assertion.
 *
 * The index query carries both listing counts as subselects, so the whole page
 * is one categories query, one media load and the pagination count.
 */
test('reports a category as not deletable in the index payload', function () {
    $admin = Admin::factory()->create();
    Category::factory()->count(3)->create();

    DB::flushQueryLog();
    DB::enableQueryLog();

    $this->actingAs($admin, 'admin')
        ->getJson('/nova-api/categories')
        ->assertOk()
        ->assertJsonCount(3, 'resources')
        ->assertJsonPath('resources.0.authorizedToDelete', false)
        ->assertJsonPath('resources.0.authorizedToUpdate', true)
        ->assertJsonPath('resources.2.authorizedToDelete', false);

    expect(DB::getQueryLog())->toHaveCount(3);
});
