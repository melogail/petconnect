<?php

use App\Http\Controllers\Settings\ProfileController;
use App\Http\Controllers\Settings\SecurityController;
use Illuminate\Auth\Middleware\RequirePassword;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Profile settings
|--------------------------------------------------------------------------
|
| `profile.update` is throttled by `profile-updates`
| (AppServiceProvider::configureRateLimiters). It is the second of the two
| writes in the application that cost image-conversion CPU rather than a row:
| an avatar upload is stored and put through two conversions, and it carried
| no ceiling at all — measured, one account uploaded and converted 25 avatars
| in a single burst, all 302. 10 a minute is a human saving a form and then
| correcting a typo; it is a ceiling on a script, not on a person.
|
*/

Route::middleware(['auth'])->group(function () {
    Route::redirect('settings', '/settings/profile');

    Route::get('settings/profile', [ProfileController::class, 'edit'])->name('profile.edit');

    Route::patch('settings/profile', [ProfileController::class, 'update'])
        ->middleware('throttle:profile-updates')
        ->name('profile.update');
});

Route::middleware(['auth', 'verified'])->group(function () {
    Route::delete('settings/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');

    Route::get('settings/security', [SecurityController::class, 'edit'])
        ->middleware(RequirePassword::class)
        ->name('security.edit');

    Route::put('settings/password', [SecurityController::class, 'update'])
        ->middleware('throttle:6,1')
        ->name('user-password.update');

    Route::inertia('settings/appearance', 'settings/Appearance')->name('appearance.edit');
});

Route::get('.well-known/passkey-endpoints', function () {
    return response()->json([
        'enroll' => route('security.edit'),
        'manage' => route('security.edit'),
    ]);
})->name('well-known.passkeys');
