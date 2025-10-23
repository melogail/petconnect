<?php

use App\Http\Controllers\Web\HomeController;
use App\Http\Controllers\Web\PetController;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

// Home
Route::get('/', [HomeController::class, 'index'])->name('home');

// Pet details page
Route::get('pets/{pet}', [PetController::class, 'show'])->name('pets.show');


// Authenticated Routes
Route::middleware('auth')->group(function () {


});

require __DIR__.'/settings.php';
require __DIR__.'/auth.php';
