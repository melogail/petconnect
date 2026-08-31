<?php

use App\Models\Admin;
use App\Models\Pet;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

/**
 * `users` has no soft deletes and eight foreign keys cascade off it, and a
 * database cascade fires no Eloquent event — so Nova's built-in delete would
 * strand every polymorphic row that reaches the account through a morph column
 * and leave its uploaded files on disk. App\Nova\Policies\UserPolicy::delete
 * returns false to take that button away entirely, and
 * App\Nova\Actions\DeleteUserAccount is the only route to a deleted account.
 *
 * The refusal is asserted as "nothing was removed" rather than as a status
 * code, because Nova answers a delete of an unauthorised resource with 200 and
 * an empty result: the resource is filtered out of the deletable set instead of
 * the request being rejected. A test that only asserted the status would pass
 * against a policy returning true.
 */
test('removes nothing when the built-in delete is aimed at a member account', function () {
    $admin = Admin::factory()->create();
    $user = User::factory()->create();
    $listing = Pet::factory()->for($user)->create();

    $this->actingAs($admin, 'admin')
        ->deleteJson('/nova-api/users', ['resources' => [$user->getKey()]])
        ->assertOk();

    $this->assertModelExists($user);
    $this->assertModelExists($listing);
});

test('removes nothing when the built-in delete selects every member account', function () {
    $admin = Admin::factory()->create();
    User::factory()->count(3)->create();

    $this->actingAs($admin, 'admin')
        ->deleteJson('/nova-api/users', ['resources' => 'all'])
        ->assertOk();

    expect(User::query()->count())->toBe(3);
});

/**
 * The flag the front end reads to decide whether to draw the delete control at
 * all, on the detail page, the row menu and the index's bulk actions.
 *
 * Three accounts rather than one, and no `Model::preventLazyLoading(false)`.
 * The suppression used to be here because AppServiceProvider::isNovaRequest()
 * early-returned on `runningInConsole()` — true under Pest even for a simulated
 * HTTP request — so Nova's exemption from the guardrail was dead in tests and
 * the index 500'd on the Avatar field's media lookup. It now also asks
 * `runningUnitTests()`, and App\Nova\User declares `$with = ['media']`.
 *
 * The row count is the other half. Builder::hydrate() only arms
 * `preventsLazyLoading` above one row, so a single-row fixture proved nothing
 * either way; and because Nova *is* exempt, a missing eager load here would not
 * throw even now — it would silently issue one media query per account. So the
 * cost is counted: one page of accounts, one media load for the page, one
 * pagination count, whatever the page holds.
 */
test('reports a member account as not deletable in the index payload', function () {
    $admin = Admin::factory()->create();
    User::factory()->count(3)->create();

    DB::flushQueryLog();
    DB::enableQueryLog();

    $this->actingAs($admin, 'admin')
        ->getJson('/nova-api/users')
        ->assertOk()
        ->assertJsonCount(3, 'resources')
        ->assertJsonPath('resources.0.authorizedToDelete', false)
        ->assertJsonPath('resources.0.authorizedToForceDelete', false)
        ->assertJsonPath('resources.0.authorizedToRestore', false)
        ->assertJsonPath('resources.0.authorizedToReplicate', false)
        ->assertJsonPath('resources.2.authorizedToDelete', false);

    expect(DB::getQueryLog())->toHaveCount(3);
});

/**
 * Accounts are created by registering. A Nova create form would bypass
 * Actions\Users\RegisterUser — the unique media-directory draw and the
 * verification mail — and produce an account nobody asked for.
 */
test('returns 403 to a request for the member creation form', function () {
    $this->actingAs(Admin::factory()->create(), 'admin')
        ->getJson('/nova-api/users/creation-fields')
        ->assertForbidden();
});

test('returns 403 to a member account stored through Nova', function () {
    $this->actingAs(Admin::factory()->create(), 'admin')
        ->postJson('/nova-api/users', ['name' => 'Walked In', 'email' => 'walked-in@example.com'])
        ->assertForbidden();

    $this->assertDatabaseEmpty('users');
});

/**
 * Editing is allowed, and bounded by App\Nova\User's field list rather than by
 * the policy: `email` is display only so `email_verified_at` cannot end up
 * pointing at an address nobody confirmed, `is_active` is display only so the
 * Deactivate / Reactivate actions stay its single writer, and there is no
 * password field at all. Nova fills by direct property assignment, so mass
 * assignment guarding would not have caught any of the three — the field list
 * is the whole defence, and this sends all three anyway.
 */
test('applies only the fields the resource exposes when a member profile is edited', function () {
    $admin = Admin::factory()->create();
    $user = User::factory()->create(['name' => 'Original Name']);

    $this->actingAs($admin, 'admin')
        ->putJson("/nova-api/users/{$user->getKey()}", [
            'name' => 'Edited Name',
            'locale' => 'en',
            'email' => 'taken-over@example.com',
            'is_active' => false,
            'password' => 'a-new-secret-password',
        ])
        ->assertOk();

    expect($user->fresh())
        ->name->toBe('Edited Name')
        ->email->toBe($user->email)
        ->is_active->toBeTrue();

    expect(Hash::check('a-new-secret-password', $user->fresh()->password))->toBeFalse();
});
