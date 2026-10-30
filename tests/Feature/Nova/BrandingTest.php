<?php

use App\Models\Admin;

test('the nova shell carries the brand logo and the app colour ramp', function () {
    $this->actingAs(Admin::factory()->create(), 'admin')
        ->get('/nova/dashboards/main')
        ->assertOk()
        ->assertSee('SniffPal<\\/title>', escape: false)
        ->assertSee('--colors-primary-600: 90, 63, 224', escape: false);
});
