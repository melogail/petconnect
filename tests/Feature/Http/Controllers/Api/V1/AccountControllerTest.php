<?php

use App\Models\User;
use Laravel\Sanctum\Sanctum;

describe('me', function () {
    test('requires a token', function () {
        $this->getJson(route('api.v1.me.show'))->assertUnauthorized();
    });

    test('returns the account with its verification state', function () {
        $user = User::factory()->unverified()->create(['city' => 'Cairo', 'state' => null, 'country' => 'Egypt']);
        Sanctum::actingAs($user);

        $this->getJson(route('api.v1.me.show'))
            ->assertOk()
            ->assertJsonPath('id', $user->getKey())
            ->assertJsonPath('is_verified', false)
            ->assertJsonPath('two_factor_enabled', false)
            ->assertJsonPath('location', 'Cairo, Egypt');
    });

    test('updates the profile through the settings form request', function () {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $this->patchJson(route('api.v1.me.update'), [
            'name' => 'Renamed',
            'email' => $user->email,
            'bio' => 'Cat person.',
            'locale' => 'ar',
        ])
            ->assertOk()
            ->assertJsonPath('name', 'Renamed')
            ->assertJsonPath('bio', 'Cat person.')
            ->assertJsonPath('locale', 'ar');

        expect($user->fresh()->name)->toBe('Renamed');
    });

    test('an unverified account may still edit its profile', function () {
        $user = User::factory()->unverified()->create();
        Sanctum::actingAs($user);

        $this->patchJson(route('api.v1.me.update'), ['name' => 'Renamed', 'email' => $user->email])
            ->assertOk();
    });

    test('reports the tab badges', function () {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $this->getJson(route('api.v1.me.badges'))
            ->assertOk()
            ->assertExactJson(['unread_conversations' => 0, 'unread_notifications' => 0]);
    });
});

describe('password', function () {
    test('changes the password and revokes every other token', function () {
        $user = User::factory()->create();
        $current = $user->createToken('phone')->plainTextToken;
        $user->createToken('tablet');

        $this->withToken($current)->putJson(route('api.v1.me.password.update'), [
            'current_password' => 'password',
            'password' => 'new-password-123',
            'password_confirmation' => 'new-password-123',
        ])->assertNoContent();

        expect($user->tokens()->pluck('name')->all())->toBe(['phone']);
        $this->withToken($current)->getJson(route('api.v1.me.show'))->assertOk();
    });

    test('rejects a wrong current password', function () {
        Sanctum::actingAs(User::factory()->create());

        $this->putJson(route('api.v1.me.password.update'), [
            'current_password' => 'wrong',
            'password' => 'new-password-123',
            'password_confirmation' => 'new-password-123',
        ])->assertInvalid(['current_password']);
    });
});

describe('destroy', function () {
    test('deletes the account and its tokens after a password check', function () {
        $user = User::factory()->create();
        $token = $user->createToken('phone')->plainTextToken;

        $this->withToken($token)->deleteJson(route('api.v1.me.destroy'), ['password' => 'password'])
            ->assertNoContent();

        $this->assertModelMissing($user);
        $this->assertDatabaseCount('personal_access_tokens', 0);
    });

    test('an unverified account cannot delete itself', function () {
        Sanctum::actingAs(User::factory()->unverified()->create());

        $this->deleteJson(route('api.v1.me.destroy'), ['password' => 'password'])->assertForbidden();
    });
});

describe('middleware', function () {
    test('a deactivated bearer is refused and the token revoked', function () {
        $user = User::factory()->create();
        $token = $user->createToken('phone')->plainTextToken;
        $user->forceFill(['is_active' => false])->save();

        $this->withToken($token)->getJson(route('api.v1.me.show'))->assertForbidden();

        expect($user->tokens()->count())->toBe(0);
    });

    test('the X-Locale header selects the language of the response', function () {
        Sanctum::actingAs(User::factory()->create());

        $english = $this->patchJson(route('api.v1.me.update'), ['email' => 'x@example.com'])->json('errors.name.0');
        $arabic = $this->withHeader('X-Locale', 'ar')
            ->patchJson(route('api.v1.me.update'), ['email' => 'x@example.com'])
            ->json('errors.name.0');

        expect($english)->toBeString()->and($arabic)->toBeString()->not->toBe($english);
    });
});
