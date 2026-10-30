<?php

use App\Models\User;
use Inertia\Testing\AssertableInertia;

test('privacy page is displayed with the member own flags', function () {
    $user = User::factory()->create(['show_phone' => true]);

    $this->actingAs($user)
        ->get(route('privacy.edit'))
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->component('settings/Privacy')
            ->where('profile.show_phone', true)
            ->where('profile.whatsapp_enabled', false));
});

test('privacy page requires a signed-in member', function () {
    $this->get(route('privacy.edit'))->assertRedirect(route('login'));
});

test('saving from the privacy page lands back on it', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->from(route('privacy.edit'))
        ->patch(route('profile.update'), [
            'name'             => $user->name,
            'email'            => $user->email,
            'whatsapp_enabled' => true,
        ])
        ->assertSessionHasNoErrors()
        ->assertRedirect(route('privacy.edit'));

    expect($user->refresh()->whatsapp_enabled)->toBeTrue();
});
