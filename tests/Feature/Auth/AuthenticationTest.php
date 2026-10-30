<?php

use App\Models\User;
use Illuminate\Support\Facades\RateLimiter;
use Laravel\Fortify\Features;

test('login screen can be rendered', function () {
    $response = $this->get(route('login'));

    $response->assertOk();
});

test('users can authenticate using the login screen', function () {
    $user     = User::factory()->create();

    $response = $this->post(route('login.store'), [
        'email'    => $user->email,
        'password' => 'password',
    ]);

    $this->assertAuthenticated();
    $response->assertRedirect(route('home', absolute: false));
});

test('login ignores an intended url stored by another host', function () {
    $user     = User::factory()->create();

    $response = $this->withSession(['url.intended' => 'https://nova.'.request()->getHost().'/dashboards/main'])
        ->post(route('login.store'), [
            'email'    => $user->email,
            'password' => 'password',
        ]);

    $this->assertAuthenticated();
    $response->assertRedirect(route('home', absolute: false));
    $response->assertSessionMissing('url.intended');
});

test('login still honours an intended url on its own host', function () {
    $user     = User::factory()->create();

    $response = $this->withSession(['url.intended' => route('pets.create')])
        ->post(route('login.store'), [
            'email'    => $user->email,
            'password' => 'password',
        ]);

    $response->assertRedirect(route('pets.create'));
});

test('users with two factor enabled are redirected to two factor challenge', function () {
    $this->skipUnlessFortifyHas(Features::twoFactorAuthentication());

    Features::twoFactorAuthentication([
        'confirm'         => true,
        'confirmPassword' => true,
    ]);

    $user     = User::factory()->withTwoFactor()->create();

    $response = $this->post(route('login'), [
        'email'    => $user->email,
        'password' => 'password',
    ]);

    $response->assertRedirect(route('two-factor.login'));
    $response->assertSessionHas('login.id', $user->id);
    $this->assertGuest();
});

test('users can not authenticate with invalid password', function () {
    $user = User::factory()->create();

    $this->post(route('login.store'), [
        'email'    => $user->email,
        'password' => 'wrong-password',
    ]);

    $this->assertGuest();
});

test('users can logout', function () {
    $user     = User::factory()->create();

    $response = $this->actingAs($user)->post(route('logout'));

    $response->assertRedirect(route('home'));

    $this->assertGuest();
});

test('users are rate limited', function () {
    $user     = User::factory()->create();

    RateLimiter::increment(md5('login'.implode('|', [$user->email, '127.0.0.1'])), amount: 5);

    $response = $this->post(route('login.store'), [
        'email'    => $user->email,
        'password' => 'wrong-password',
    ]);

    $response->assertTooManyRequests();
});
