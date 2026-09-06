<?php

use App\Models\Admin;
use App\Models\User;
use Illuminate\Support\Facades\Event;

/**
 * The other half of DeactivateUser. Reactivation is a plain reversal — the
 * session survives, the public profile becomes visible again, messages are
 * accepted again — because deactivation never touched the account's content in
 * the first place.
 *
 * It is a separate action rather than a toggle on purpose: one confirmation run
 * over a mixed selection would deactivate half the rows and reactivate the
 * other half, which is not something a moderator can mean. That is what the
 * mixed-selection case below pins.
 */
test('reactivates a deactivated member account', function () {
    $admin = Admin::factory()->create();
    $member = User::factory()->inactive()->create();

    $this->actingAs($admin, 'admin')
        ->postJson('/nova-api/users/action?action=reactivate-account', [
            'resources' => [$member->getKey()],
        ])
        ->assertOk()
        ->assertJsonPath('message', '1 account(s) reactivated.');

    expect($member->fresh()->is_active)->toBeTrue();
});

/**
 * Nova and the application are two Inertia apps in one process, and their
 * shared props live on the same singleton, so the application request below
 * follows a Nova one deliberately: Nova's `novaConfig` closure is still
 * registered on the container and would be resolved while rendering the
 * member's page, serialising the whole back-office configuration into a public
 * page's props. Http\Middleware\HandleInertiaRequests::handle() flushes the
 * shared props before it registers this application's own, which is why no
 * `Inertia::flushShared()` is needed here any more.
 */
test('lets the reactivated account use the application again', function () {
    $admin = Admin::factory()->create();
    $member = User::factory()->inactive()->create();

    $this->actingAs($admin, 'admin')
        ->postJson('/nova-api/users/action?action=reactivate-account', [
            'resources' => [$member->getKey()],
        ])
        ->assertOk();

    $this->actingAs($member->fresh())->get(route('profile.edit'))->assertOk();

    $this->assertAuthenticatedAs($member);
});

test('leaves an already active account alone in a mixed selection', function () {
    $admin = Admin::factory()->create();
    $deactivated = User::factory()->inactive()->create();
    $active = User::factory()->create();

    $this->actingAs($admin, 'admin')
        ->postJson('/nova-api/users/action?action=reactivate-account', [
            'resources' => [$deactivated->getKey(), $active->getKey()],
        ])
        ->assertOk()
        ->assertJsonPath('message', '1 account(s) reactivated.');

    expect($deactivated->fresh()->is_active)->toBeTrue()
        ->and($active->fresh()->is_active)->toBeTrue();
});

test('reports that there was nothing to do when every selected account is already active', function () {
    $admin = Admin::factory()->create();
    $active = User::factory()->create();

    $this->actingAs($admin, 'admin')
        ->postJson('/nova-api/users/action?action=reactivate-account', [
            'resources' => [$active->getKey()],
        ])
        ->assertOk()
        ->assertJsonPath('message', 'Nothing to do: every selected account was already active.');

    expect($active->fresh()->is_active)->toBeTrue();
});

/**
 * The mirror of DeactivateUser's rollback, and the false success is the same
 * shape: a catch that fell through, or an `$affected` initialised before the
 * try, would land on the `=== 0` branch and answer a run that threw and rolled
 * back with "Nothing to do: every selected account was already active." Both
 * selected accounts are deactivated here, so that sentence would be a plain
 * lie — and the admin who read it would leave two people locked out believing
 * they were not. Hence the assertion that no success message is present at
 * all, alongside the danger one.
 *
 * The throw is injected on the second save, so the first account has genuinely
 * been reactivated and rolled back rather than never having been touched, which
 * is what `$reactivatedMidFlight` records.
 */
test('leaves every selected account deactivated when one of them cannot be saved', function () {
    $admin = Admin::factory()->create();
    $first = User::factory()->inactive()->create();
    $second = User::factory()->inactive()->create();
    $attempts = 0;
    $reactivatedMidFlight = null;

    Event::listen('eloquent.saving: '.User::class, function () use (&$attempts, &$reactivatedMidFlight): void {
        if (++$attempts === 1) {
            return;
        }

        $reactivatedMidFlight = User::query()->where('is_active', true)->count();

        throw new RuntimeException('The account could not be saved.');
    });

    $this->actingAs($admin, 'admin')
        ->postJson('/nova-api/users/action?action=reactivate-account', [
            'resources' => [$first->getKey(), $second->getKey()],
        ])
        ->assertOk()
        ->assertJsonPath('danger', 'Nothing was changed. One of the selected accounts could not be reactivated, so the whole selection was rolled back. The failure has been logged.')
        ->assertJsonMissingPath('message');

    expect($reactivatedMidFlight)->toBe(1)
        ->and($first->fresh()->is_active)->toBeFalse()
        ->and($second->fresh()->is_active)->toBeFalse();
});
