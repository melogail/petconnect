<?php

use App\Models\Admin;
use App\Models\User;

/**
 * Every registered member account, all time.
 *
 * The trap is in `Value::aggregate()`, not in the label. It short-circuits to a
 * plain unfiltered count only when `$request->range === 'ALL'`; otherwise it
 * does `$range = $request->range ?? 1` and constrains `created_at` to that many
 * days. A Value with no `ranges()` renders no range control, so the front end
 * sends no `range` — the fallback fires and a card headed "Total Users" quietly
 * reports the last twenty-four hours, growth arrow and all. On a seeded
 * database it reads 0 while the table holds hundreds.
 *
 * So the fixture is deliberately made of members who signed up weeks apart and
 * the request deliberately carries no range: a metric that went back through
 * the ranged helper would answer 1 here, which is a number nobody would
 * question.
 */
test('counts every member however long ago they signed up', function () {
    $this->freezeTime();
    User::factory()->create(['created_at' => now()->subYear()]);
    User::factory()->create(['created_at' => now()->subWeeks(6)]);
    User::factory()->create(['created_at' => now()->subDays(2)]);
    User::factory()->create(['created_at' => now()]);

    $this->actingAs(Admin::factory()->create(), 'admin')
        ->getJson('/nova-api/dashboards/cards/main/metrics/total-users')
        ->assertOk()
        ->assertJsonPath('value.value', 4);
});

test('returns zero rather than no data for an empty users table', function () {
    $this->assertDatabaseEmpty('users');

    $this->actingAs(Admin::factory()->create(), 'admin')
        ->getJson('/nova-api/dashboards/cards/main/metrics/total-users')
        ->assertOk()
        ->assertJsonPath('value.value', 0)
        ->assertJsonPath('value.zeroResult', true);
});

/**
 * Back-office accounts live in `admins` and are not members. Counting the
 * signed-in admin would inflate the marketplace's headline number by however
 * many moderators there are.
 */
test('does not count back-office accounts', function () {
    Admin::factory()->count(3)->create();

    $this->actingAs(Admin::factory()->create(), 'admin')
        ->getJson('/nova-api/dashboards/cards/main/metrics/total-users')
        ->assertOk()
        ->assertJsonPath('value.value', 0);
});
