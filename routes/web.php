<?php

use App\Http\Controllers\Web\HomeController;
use App\Http\Controllers\Web\PetController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Public
|--------------------------------------------------------------------------
|
| The discovery feed and a single listing are reachable without an account:
| a shared link has to work for somebody who has never signed in. Both
| payloads hide the owner-only fields.
|
| Every route is named, because the frontend calls them through Wayfinder.
| No controller method is registered at two URIs: Wayfinder emits a
| URI-keyed object instead of a callable when it sees a duplicate, which
| breaks the generated import at runtime.
|
*/

Route::get('/', [HomeController::class, 'index'])->name('home');

Route::get('pets/{pet}', [PetController::class, 'show'])
    ->whereNumber('pet')
    ->name('pets.show');

/*
|--------------------------------------------------------------------------
| Listing management
|--------------------------------------------------------------------------
|
| Publishing, editing and liking need a verified account; ownership on top of
| that is decided by PetPolicy, which every action calls with
| $this->authorize(). The pet parameter is constrained to digits so
| `pets/create` can never be swallowed by `pets/{pet}`.
|
| The like route is throttled: it is a POST that writes a like, which fires
| LikeObserver and sends the owner a database notification, so an unthrottled
| tap loop is a notification flood.
|
*/

Route::middleware(['auth', 'verified'])->group(function (): void {
    Route::get('pets/create', [PetController::class, 'create'])->name('pets.create');
    Route::post('pets', [PetController::class, 'store'])->name('pets.store');

    Route::inertia('dashboard', 'Dashboard')->name('dashboard');

    Route::whereNumber('pet')->group(function (): void {
        Route::get('pets/{pet}/edit', [PetController::class, 'edit'])->name('pets.edit');
        Route::put('pets/{pet}', [PetController::class, 'update'])->name('pets.update');
        Route::delete('pets/{pet}', [PetController::class, 'destroy'])->name('pets.destroy');

        Route::patch('pets/{pet}/status', [PetController::class, 'toggleStatus'])
            ->name('pets.status.toggle');

        Route::post('pets/{pet}/like', [PetController::class, 'toggleLike'])
            ->middleware('throttle:pet-likes')
            ->name('pets.like');
    });
});

require __DIR__.'/settings.php';
