<?php

use App\Models\Admin;
use App\Models\Breed;
use App\Models\Category;
use App\Models\Comment;
use App\Models\Pet;
use App\Models\Report;
use App\Models\Review;
use App\Models\User;
use Illuminate\Database\Eloquent\Relations\Relation;
use Laravel\Nova\Actions\ActionEvent;
use Tests\TestCase;

/**
 * The morph map is enforced, and Nova is the only part of the application that
 * writes a morph value for every one of the eight models.
 *
 * `action_events` carries three morph columns — `actionable_type`,
 * `target_type` and `model_type` — and Nova fills all three on every write it
 * records: an action run, and also a built-in create, update or delete.
 * Because AppServiceProvider::configureMorphMap() calls enforceMorphMap(), a
 * model missing from that map throws ClassMorphViolationException the first
 * time anybody touches it in the back office, which is a 500 on a moderator's
 * screen rather than anything visible in development. `report` was the entry
 * that was missing when the Report resource was added.
 *
 * So the map is exercised through the write that would break, once per
 * resource, and the alias is asserted as the literal short string it must be —
 * a fully qualified class name in these columns is the failure this catches.
 */
test('records a nova write against a short morph alias', function (string $alias, Closure $write) {
    $admin = Admin::factory()->create();

    $write($this, $admin);

    $event = ActionEvent::query()->latest('id')->first();

    expect($event)->not->toBeNull()
        ->and($event->actionable_type)->toBe($alias)
        ->and($event->target_type)->toBe($alias)
        ->and($event->model_type)->toBe($alias)
        ->and(Relation::getMorphedModel($alias))->not->toBeNull();
})->with([
    'user, deactivated by an action' => ['user', function (TestCase $test, Admin $admin): void {
        $user = User::factory()->create();

        $test->actingAs($admin, 'admin')
            ->postJson('/nova-api/users/action?action=deactivate-account', ['resources' => [$user->getKey()]])
            ->assertOk();
    }],
    'admin, removed by the built-in delete' => ['admin', function (TestCase $test, Admin $admin): void {
        $colleague = Admin::factory()->create();

        $test->actingAs($admin, 'admin')
            ->deleteJson('/nova-api/admins', ['resources' => [$colleague->getKey()]])
            ->assertOk();
    }],
    'pet, retired by the built-in delete' => ['pet', function (TestCase $test, Admin $admin): void {
        $pet = Pet::factory()->create();

        $test->actingAs($admin, 'admin')
            ->deleteJson('/nova-api/pets', ['resources' => [$pet->getKey()]])
            ->assertOk();
    }],
    'breed, removed by the built-in delete' => ['breed', function (TestCase $test, Admin $admin): void {
        $breed = Breed::factory()->create();

        $test->actingAs($admin, 'admin')
            ->deleteJson('/nova-api/breeds', ['resources' => [$breed->getKey()]])
            ->assertOk();
    }],
    'category, removed by an action' => ['category', function (TestCase $test, Admin $admin): void {
        $category = Category::factory()->create();

        $test->actingAs($admin, 'admin')
            ->postJson('/nova-api/categories/action?action=delete-category', ['resources' => [$category->getKey()]])
            ->assertOk();
    }],
    'comment, removed by an action' => ['comment', function (TestCase $test, Admin $admin): void {
        $comment = Comment::factory()->forPet()->create();

        $test->actingAs($admin, 'admin')
            ->postJson('/nova-api/comments/action?action=delete-comment-with-replies', ['resources' => [$comment->getKey()]])
            ->assertOk();
    }],
    'review, removed by an action' => ['review', function (TestCase $test, Admin $admin): void {
        $review = Review::factory()->create();

        $test->actingAs($admin, 'admin')
            ->postJson('/nova-api/reviews/action?action=delete-review-with-reports', ['resources' => [$review->getKey()]])
            ->assertOk();
    }],
    'report, decided by an action' => ['report', function (TestCase $test, Admin $admin): void {
        $report = Report::factory()->pending()->create();

        $test->actingAs($admin, 'admin')
            ->postJson('/nova-api/reports/action?action=change-status', [
                'resources' => [$report->getKey()],
                'status' => 'resolved',
            ])
            ->assertOk();
    }],
]);
