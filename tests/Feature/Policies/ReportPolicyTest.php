<?php

use App\Models\Report;
use App\Models\User;

test('a verified user may file a report', function () {
    expect(User::factory()->create()->can('create', Report::class))->toBeTrue();
});

/**
 * Filing a report puts an item in a moderator's queue and names another user's
 * content. Requiring verification is the cheapest brake on someone creating
 * accounts to bury that queue; `throttle:reports` on the route is the other.
 */
test('an unverified user may not file a report', function () {
    expect(User::factory()->unverified()->create()->can('create', Report::class))->toBeFalse();
});
