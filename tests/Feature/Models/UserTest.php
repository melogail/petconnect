<?php

use App\Models\User;
use Illuminate\Database\Eloquent\MassAssignmentException;

test('rejects a mass assigned privileged column', function (string $column, mixed $value) {
    $user = User::factory()->create();

    expect(fn () => $user->fill([$column => $value]))
        ->toThrow(MassAssignmentException::class);
})->with([
    'account activation' => ['is_active', false],
    'two factor secret' => ['two_factor_secret', 'secret'],
    'two factor recovery codes' => ['two_factor_recovery_codes', 'codes'],
    'two factor confirmation' => ['two_factor_confirmed_at', '2026-01-01 00:00:00'],
]);

test('leaves a deactivated account deactivated when an update payload asks to reactivate it', function () {
    $user = User::factory()->inactive()->create();

    expect(fn () => $user->update(['name' => 'Sara', 'is_active' => true]))
        ->toThrow(MassAssignmentException::class);

    expect($user->fresh()->is_active)->toBeFalse();
});

test('creates no account when the payload carries a privileged column', function () {
    expect(fn () => User::create([
        'name' => 'Sara',
        'email' => 'sara@example.com',
        'password' => 'password',
        'two_factor_confirmed_at' => now(),
    ]))->toThrow(MassAssignmentException::class);

    $this->assertDatabaseEmpty('users');
});
