<?php

use App\Http\Controllers\Web\HomeController;
use App\Http\Controllers\Web\PetController;
use App\Http\Controllers\Web\ProfileController;
use App\Http\Controllers\Web\SupportController;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

// Home page
Route::get('/', [HomeController::class, 'index'])->name('home');


// Support and Help Routes
Route::get('/support', function () {
    return Inertia::render('Support/Index');
})->name('support');

Route::get('/help', function () {
    return Inertia::render('Help/Index');
})->name('help');

// Authenticated Routes
Route::middleware('auth')->group(function () {
    Route::resource('profile', ProfileController::class)
        ->except(['index', 'create', 'store'])->names('user-profile');
    // Pet create page
    Route::get('pets/create', [PetController::class, 'create'])->name('pets.create');
    Route::post('pets/store', [PetController::class, 'store'])->name('pets.store');

});

// Pet details page
Route::get('pets/{pet}', [PetController::class, 'show'])->name('pets.show');

require __DIR__.'/settings.php';
require __DIR__.'/auth.php';
