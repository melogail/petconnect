<?php

use App\Models\Admin;
use App\Models\User;

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

    $this->actingAs($member->fresh())->get(route('dashboard'))->assertOk();

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
