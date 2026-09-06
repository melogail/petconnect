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
| (AppServiceProvider::configureRateLimiters). It is one of the writes in the
| application that cost image-conversion CPU rather than a row: an avatar
| upload is stored and put through two conversions, and it carried no ceiling
| at all — measured, one account uploaded and converted 25 avatars in a single
| burst, all 302. 10 a minute is a human saving a form and then correcting a
| typo; it is a ceiling on a script, not on a person.
|
| The reasoning is the same one `pets.store` and `pets.update` now use, and
| deliberately so: what is being bounded is conversion CPU on a web worker,
| which no policy bounds, because owning the row a request rewrites says
| nothing about how often it may be rewritten. This route is the *cheapest* of
| the three — one image against four — so it cannot be the throttled one while
| a heavier route is not.
|
*/

Route::middleware(['auth'])->group(function () {
    Route::redirect('settings', '/settings/profile');

    Route::get('settings/profile', [ProfileController::class, 'edit'])->name('profile.edit');

    Route::patch('settings/profile', [ProfileController::class, 'update'])
        ->middleware('throttle:profile-updates')
        ->name('profile.update');
});

/*
|--------------------------------------------------------------------------
| Account deletion
|--------------------------------------------------------------------------
|
| `profile.destroy` was the last mutating route in the application with no
| ceiling of any kind. It is throttled by `account-deletions`
| (AppServiceProvider::configureRateLimiters) — 10 a minute, 20 an hour, keyed
| on the caller like every other authenticated limiter here.
|
| It is the heaviest write in the app: Actions\Profiles\DeleteUserAccount runs
| nine pipeline steps in one transaction, deleting across pets, comments,
| reviews, likes, saves, reports, messages, conversation_user and
| notifications, and removing the media files of every listing the account
| owns — a filesystem half no rollback undoes.
|
| The ceiling is generous on purpose. The legitimate use is one request in the
| lifetime of an account, so anything repeating is a client retrying a
| *failing* delete: a rejected `current_password`, or a step that threw after
| the first file was already gone. This is a backstop against that loop, not a
| usage cap. It also bounds the `current_password` Hash::check in
| DeleteProfileRequest, which is the same password oracle
| `password-confirmations` guards.
|
| Not on `content-edits` with the other authenticated destroys: that bucket is
| sized for repeatable edits to a fixed set of owned rows, and an afternoon of
| editing must not spend the allowance for leaving.
|
*/

Route::middleware(['auth', 'verified'])->group(function () {
    Route::delete('settings/profile', [ProfileController::class, 'destroy'])
        ->middleware('throttle:account-deletions')
        ->name('profile.destroy');

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
