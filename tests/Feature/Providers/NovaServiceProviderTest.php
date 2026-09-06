<?php

use App\Models\Admin;
use App\Models\Report;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Testing\TestResponse;

/**
 * The boundary between the marketplace and the back office.
 *
 * Two independent things keep a member out of Nova, and both are pinned here
 * because either one alone is a single point of failure:
 *
 * 1. **The guard.** `config('nova.guard')` is `admin`, whose provider is
 *    `admins`, whose model is App\Models\Admin. Nova's Authenticate middleware
 *    resolves the acting user from that guard, so a session on `web` is simply
 *    not authenticated as far as Nova is concerned. Laravel\Nova\Util::userGuard()
 *    falls back to `config('auth.defaults.guard')` — `web` — when the value is
 *    null, and a null there would hand the whole back office to every
 *    registered member, so the value itself is asserted as well as its effect.
 * 2. **The `viewNova` gate**, defined in App\Providers\NovaServiceProvider,
 *    which answers false for anything that is not an App\Models\Admin. It is
 *    the belt to the guard's braces.
 */
test('lets an admin on the admin guard reach the dashboard', function () {
    $this->actingAs(Admin::factory()->create(), 'admin')
        ->get('/nova/dashboards/main')
        ->assertOk();
});

test('redirects a member on the web guard to the nova login page', function () {
    $this->actingAs(User::factory()->create())
        ->get('/nova/dashboards/main')
        ->assertRedirect('/nova/login');
});

test('returns 401 to a member on the web guard from a nova api endpoint', function () {
    $this->actingAs(User::factory()->create())
        ->getJson('/nova-api/users')
        ->assertUnauthorized();
});

test('returns 401 to a guest from a nova api endpoint', function () {
    $this->getJson('/nova-api/users')->assertUnauthorized();
});

/**
 * A member has no session on the `admin` guard, so no member id can ever be
 * mistaken for an admin id, whatever their primary keys happen to be. Asserted
 * with a member whose id matches an existing admin's, which is the shape that
 * would go unnoticed if the guard were ever cleared.
 */
test('does not treat a member as the admin sharing their id', function () {
    $admin = Admin::factory()->create();
    $member = User::factory()->create();

    expect($member->getKey())->toBe($admin->getKey());

    $this->actingAs($member)
        ->getJson('/nova-api/admins')
        ->assertUnauthorized();
});

test('reads its user from the admin guard rather than the application default', function () {
    expect(config('nova.guard'))->toBe('admin')
        ->and(config('auth.defaults.guard'))->toBe('web')
        ->and(config('auth.guards.admin.provider'))->toBe('admins')
        ->and(config('auth.providers.admins.model'))->toBe(Admin::class);
});

test('grants the viewNova gate to a back-office account', function () {
    expect(Gate::forUser(Admin::factory()->create())->allows('viewNova'))->toBeTrue();
});

/**
 * The gate's second line of defence, degrading properly rather than fatally.
 *
 * This used to read `fn (Admin $admin): bool => true`, on the premise that the
 * type hint *was* the check — "Laravel's Gate refuses to invoke a callback
 * whose first parameter cannot accept the acting user". That premise was
 * wrong in both directions. Gate::canBeCalledWithUser() short-circuits to true
 * for any non-null user and only inspects the signature for **guests**, so a
 * member reached the closure and PHP threw
 * `TypeError: Argument #1 ($admin) must be of type App\Models\Admin`. The hint
 * was a tripwire, not a gate: with `nova.guard` nulled a member got a 500
 * rather than a 403, which is the one failure mode a belt-and-braces defence
 * must not have.
 *
 * The closure now takes `mixed` and returns `$user instanceof Admin`, so the
 * refusal is a plain false. `null instanceof Admin` is false too, which is what
 * keeps a guest refused now that the signature no longer opts out of them.
 */
test('refuses the viewNova gate to a member', function () {
    expect(Gate::forUser(User::factory()->create())->allows('viewNova'))->toBeFalse();
});

test('refuses the viewNova gate to a guest', function () {
    expect(Gate::forUser(null)->allows('viewNova'))->toBeFalse();
});

/**
 * The pending count on the Reports item, serialised out of the real menu rather
 * than read off `menu()`'s return value: a MenuItem::resource() only renders
 * once Nova has authorized it against the acting admin, so calling the method
 * directly returns items that serialise to nothing.
 *
 * @return array{value: string, typeClass: string}|null
 */
function moderationReportsBadge(TestResponse $response): ?array
{
    $menu = json_decode(json_encode($response->inertiaProps()['novaConfig']['mainMenu']), true);

    $item = collect($menu)
        ->firstWhere('name', 'Moderation')['items'];

    return collect($item)->firstWhere('name', 'Reports')['badge'];
}

/**
 * The moderation queue is the only part of this panel with a backlog, and the
 * badge is how it announces itself without the dashboard being open. Withheld
 * when the queue is empty — `withBadgeIf` — because a permanent "0" is noise
 * that trains a moderator to stop reading the sidebar.
 */
test('badges the reports item with everything still awaiting a decision', function () {
    Report::factory()->count(3)->pending()->create();
    Report::factory()->resolved()->create();

    $response = $this->actingAs(Admin::factory()->create(), 'admin')
        ->get('/nova/dashboards/main')
        ->assertOk();

    expect(moderationReportsBadge($response)['value'])->toBe('3');
});

test('leaves the reports item unbadged when nothing is awaiting a decision', function () {
    Report::factory()->resolved()->create();

    $response = $this->actingAs(Admin::factory()->create(), 'admin')
        ->get('/nova/dashboards/main')
        ->assertOk();

    expect(moderationReportsBadge($response))->toBeNull();
});

/**
 * `withBadgeIf` evaluates the value callback and the condition callback
 * separately, so a `Report::pending()->count()` written inside each of them
 * would be queried twice per menu build rather than once. `menu()` takes the
 * count in its own body and both closures capture it, and that is asserted as a
 * number because it is invisible from the rendered badge — both spellings draw
 * the same sidebar.
 *
 * Two, not one, and the difference is Nova's rather than ours: a Nova page load
 * resolves the main menu **twice** — once through Nova's own
 * `HandleInertiaRequests::share()` and once through `Nova::jsonVariables()` on
 * the way to the root template — and each resolution calls this `menu()`. So the
 * page costs one count per build, which is the decision under test; moving the
 * count into the closures makes it four.
 */
test('counts the pending reports once per menu build, not once per badge callback', function () {
    Report::factory()->count(3)->pending()->create();
    $admin = Admin::factory()->create();

    DB::flushQueryLog();
    DB::enableQueryLog();

    $this->actingAs($admin, 'admin')->get('/nova/dashboards/main')->assertOk();

    $pendingCounts = collect(DB::getQueryLog())
        ->filter(fn (array $query): bool => str_contains($query['query'], 'from "reports"')
            && str_contains($query['query'], 'count(*)'))
        ->count();

    DB::disableQueryLog();

    expect($pendingCounts)->toBe(2);
});
