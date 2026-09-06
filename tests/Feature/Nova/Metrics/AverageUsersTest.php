<?php

use App\Models\Admin;
use App\Models\User;
use Tests\TestCase;

/**
 * Average sign-ups per day across the whole life of the `users` table.
 *
 * The metric this replaces had three faults at once, and each one has a case
 * here because none of them is visible from the card:
 *
 * - `->first()->created_at` with no null guard, so the very first page load of
 *   a fresh install was a fatal on null;
 * - days ÷ members assigned to a variable called `$averageUsersPerDay`, so ten
 *   members over five days printed 0.50 where the honest answer is 2.00. Both
 *   numbers are plausible, which is why the arithmetic is asserted against a
 *   fixed dataset rather than recomputed;
 * - the two endpoint queries were ordered the wrong way round, so "first" held
 *   the newest row and "last" the oldest.
 *
 * Read through the dashboard card endpoint rather than by calling calculate()
 * directly, because a rangeless Value is precisely where Nova's ranged helpers
 * go wrong — see AverageUsers' own docblock and TotalUsersTest — and the
 * request is the thing that does or does not carry a range.
 */
function averageUsersPerDay(TestCase $test): float
{
    return $test->actingAs(Admin::factory()->create(), 'admin')
        ->getJson('/nova-api/dashboards/cards/main/metrics/average-users')
        ->assertOk()
        ->json('value.value');
}

test('returns zero for an empty users table', function () {
    $this->assertDatabaseEmpty('users');

    expect(averageUsersPerDay($this))->toBe(0.0);
});

test('divides members by days rather than days by members', function () {
    $this->freezeTime();
    User::factory()->create(['created_at' => now()->subDays(5)]);
    User::factory()->count(9)->create(['created_at' => now()]);

    expect(averageUsersPerDay($this))->toBe(2.0);
});

/**
 * A table whose rows all landed today spans zero days. The floor of one day is
 * what turns that from a division by zero into "everybody who signed up today".
 */
test('reports the day count when every member signed up on the same day', function () {
    $this->freezeTime();
    User::factory()->count(3)->create(['created_at' => now()]);

    expect(averageUsersPerDay($this))->toBe(3.0);
});

/**
 * The span runs oldest to newest. Reversing the two endpoints gives a negative
 * difference, which the one-day floor swallows into 1.0 — so the reading would
 * be 2.00 rather than 0.20 and nothing would look broken.
 */
test('spans from the oldest member to the newest', function () {
    $this->freezeTime();
    User::factory()->create(['created_at' => now()->subDays(10)]);
    User::factory()->create(['created_at' => now()]);

    expect(averageUsersPerDay($this))->toBe(0.2);
});
