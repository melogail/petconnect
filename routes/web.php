<?php

use App\Http\Controllers\Web\HomeController;
use App\Http\Controllers\Web\PetController;
use App\Http\Controllers\Web\ProfileController;
use App\Http\Controllers\Web\ReviewController;
use App\Http\Controllers\Web\ReportController;
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
    // USER PROFILE ROUTES
    Route::get('profile/{user}/edit', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::get('profile/{user}', [ProfileController::class, 'show'])->name('profile.show')->withoutMiddleware('auth');
    Route::match(['put', 'post'], 'profile/{user}', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('profile/{user}', [ProfileController::class, 'destroy'])->name('profile.destroy');

    // PETS ROUTES
    Route::get('pets/{pet}', [PetController::class, 'show'])->name('pets.show')->withoutMiddleware('auth');
    Route::get('pets/create', [PetController::class, 'create'])->name('pets.create');
    Route::post('pets/store', [PetController::class, 'store'])->name('pets.store');

    // REVIEWS ROUTES
    Route::post('reviews/store/{type}/{reviewable_id}', [ReviewController::class, 'store'])->name('reviews.store');
    Route::put('reviews/update/{review}', [ReviewController::class, 'update'])->name('reviews.update');
    Route::delete('reviews/destroy/{review}', [ReviewController::class, 'destroy'])->name('reviews.destroy');

    // REPORT ROUTE
    Route::post('reports', ReportController::class)->name('reports.store');
});

// require __DIR__ . '/settings.php';
require __DIR__ . '/auth.php';
