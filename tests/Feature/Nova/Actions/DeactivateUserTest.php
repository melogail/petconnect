<?php

use App\Models\Admin;
use App\Models\Pet;
use App\Models\User;
use Illuminate\Support\Facades\Event;

/**
 * Before this action existed, `users.is_active` had no writer anywhere: it is
 * outside User's #[Fillable] and outside every Form Request by design, and
 * there is no self-service deactivation. Nova's User resource shows the flag
 * and never offers a field for it, so this and ReactivateUser are its only
 * writers — which is why the column refusing mass assignment
 * (tests/Feature/Models/UserTest.php) and this action working are two halves of
 * the same decision.
 */
test('deactivates a member account', function () {
    $admin = Admin::factory()->create();
    $member = User::factory()->create();
    $bystander = User::factory()->create();

    $this->actingAs($admin, 'admin')
        ->postJson('/nova-api/users/action?action=deactivate-account', [
            'resources' => [$member->getKey()],
        ])
        ->assertOk()
        ->assertJsonPath('message', '1 account(s) deactivated.');

    expect($member->fresh()->is_active)->toBeFalse()
        ->and($bystander->fresh()->is_active)->toBeTrue();
});

/**
 * The consequence, rather than the column. What deactivation means is settled
 * in one predicate, `User::isActive()`, and its first enforcement point is
 * Http\Middleware\EnsureAccountIsActive: the account's very next request ends
 * its session, whatever established it.
 *
 * Nova and the application are two Inertia apps in one process, and their
 * shared props live on the same singleton, so the application request below
 * follows a Nova one deliberately: Nova's `novaConfig` closure is still
 * registered on the container and would be resolved while rendering the
 * member's page, serialising the whole back-office configuration into a public
 * page's props. Http\Middleware\HandleInertiaRequests::handle() flushes the
 * shared props before it registers this application's own, which is why no
 * `Inertia::flushShared()` is needed here any more.
 */
test('leaves the deactivated account unable to use its existing session', function () {
    $admin = Admin::factory()->create();
    $member = User::factory()->create();

    $this->actingAs($admin, 'admin')
        ->postJson('/nova-api/users/action?action=deactivate-account', [
            'resources' => [$member->getKey()],
        ])
        ->assertOk();

    $this->actingAs($member->fresh())
        ->get(route('profile.edit'))
        ->assertRedirect(route('login'))
        ->assertSessionHas('status', 'Your account has been deactivated.');

    $this->assertGuest('web');
});

/**
 * Deactivation is about the account, not its content: listings, comments and
 * reviews stay published. Taking content down is a separate moderation
 * decision with its own action and its own audit trail.
 */
test('leaves the account listings published', function () {
    $admin = Admin::factory()->create();
    $member = User::factory()->create();
    $listing = Pet::factory()->for($member)->create();

    $this->actingAs($admin, 'admin')
        ->postJson('/nova-api/users/action?action=deactivate-account', [
            'resources' => [$member->getKey()],
        ])
        ->assertOk();

    $this->assertModelExists($listing);
    expect($listing->fresh()->deleted_at)->toBeNull();
});

test('counts only the accounts it actually changed in a mixed selection', function () {
    $admin = Admin::factory()->create();
    $active = User::factory()->count(2)->create();
    $alreadyOff = User::factory()->inactive()->create();

    $this->actingAs($admin, 'admin')
        ->postJson('/nova-api/users/action?action=deactivate-account', [
            'resources' => [...$active->modelKeys(), $alreadyOff->getKey()],
        ])
        ->assertOk()
        ->assertJsonPath('message', '2 account(s) deactivated.');

    expect(User::query()->where('is_active', true)->count())->toBe(0);
});

test('reports that there was nothing to do when every selected account is already deactivated', function () {
    $admin = Admin::factory()->create();
    $alreadyOff = User::factory()->inactive()->create();

    $this->actingAs($admin, 'admin')
        ->postJson('/nova-api/users/action?action=deactivate-account', [
            'resources' => [$alreadyOff->getKey()],
        ])
        ->assertOk()
        ->assertJsonPath('message', 'Nothing to do: every selected account was already deactivated.');

    expect($alreadyOff->fresh()->is_active)->toBeFalse();
});

/**
 * The false success this pins is the expensive one. `$affected` is the value
 * `DB::transaction()` returns, and the catch returns rather than falling
 * through, precisely so that a run which threw and rolled back cannot land on
 * the `=== 0` branch and tell the admin "Nothing to do: every selected account
 * was already deactivated." — a sentence that reads like a no-op, invites them
 * to move on, and leaves accounts they meant to close still open. Hence the
 * assertion that the response carries no success message at all, not merely
 * that it carries the danger one; both selected accounts are genuinely active,
 * so a fall-through would be visible here.
 *
 * The throw is injected on the second save, so the first account has genuinely
 * been deactivated and rolled back rather than never having been touched, which
 * is what `$deactivatedMidFlight` records.
 */
test('leaves every selected account active when one of them cannot be saved', function () {
    $admin = Admin::factory()->create();
    $first = User::factory()->create();
    $second = User::factory()->create();
    $attempts = 0;
    $deactivatedMidFlight = null;

    Event::listen('eloquent.saving: '.User::class, function () use (&$attempts, &$deactivatedMidFlight): void {
        if (++$attempts === 1) {
            return;
        }

        $deactivatedMidFlight = User::query()->where('is_active', false)->count();

        throw new RuntimeException('The account could not be saved.');
    });

    $this->actingAs($admin, 'admin')
        ->postJson('/nova-api/users/action?action=deactivate-account', [
            'resources' => [$first->getKey(), $second->getKey()],
        ])
        ->assertOk()
        ->assertJsonPath('danger', 'Nothing was changed. One of the selected accounts could not be deactivated, so the whole selection was rolled back. The failure has been logged.')
        ->assertJsonMissingPath('message');

    expect($deactivatedMidFlight)->toBe(1)
        ->and($first->fresh()->is_active)->toBeTrue()
        ->and($second->fresh()->is_active)->toBeTrue();
});
