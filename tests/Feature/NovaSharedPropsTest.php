<?php

use App\Models\Admin;

it('loads nova without treating admins as domain users for shared props', function () {
    $admin = Admin::factory()->create();

    $this->actingAs($admin, 'admin')
        ->followingRedirects()
        ->get('/nova')
        ->assertOk();
});
