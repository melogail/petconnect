<?php

use App\Http\Controllers\CommentController;
use App\Http\Controllers\Web\ConversationController;
use App\Http\Controllers\Web\HomeController;
use App\Http\Controllers\Web\MessageController;
use App\Http\Controllers\Web\PetController;
use App\Http\Controllers\Web\ProfileController;
use App\Http\Controllers\Web\ReportController;
use App\Http\Controllers\Web\ReviewController;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

// Home page
Route::get('/auto-login', function () {
    auth()->loginUsingId(1);

    return redirect('/pets/1/edit');
});
Route::get('/', [HomeController::class, 'index'])->name('home');

// Support and Help Routes
Route::get('/support', function () {
    return Inertia::render('Support/Index');
})->name('support');

Route::get('/help', function () {
    return Inertia::render('Help/Index');
})->name('help');

// Authenticated Routes
Route::middleware(['auth', 'verified'])->group(function () {
    // USER PROFILE ROUTES
    Route::get('profile/{user}/edit', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::get('profile/{user}', [ProfileController::class, 'show'])->name('profile.show')->withoutMiddleware('auth');
    Route::match(['put', 'post'], 'profile/{user}', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('profile/{user}', [ProfileController::class, 'destroy'])->name('profile.destroy');

    // PETS ROUTES
    Route::get('pets/create', [PetController::class, 'create'])->name('pets.create');
    Route::post('pets/store', [PetController::class, 'store'])->name('pets.store');
    Route::get('pets/{pet}', [PetController::class, 'show'])->name('pets.show')->withoutMiddleware(['auth', 'verified']);
    Route::get('pets/{pet}/edit', [PetController::class, 'edit'])->name('pets.edit');
    Route::put('pets/{pet}', [PetController::class, 'update'])->name('pets.update');
    Route::delete('pets/{pet}', [PetController::class, 'destroy'])->name('pets.destroy');

    // REVIEWS ROUTES
    Route::post('reviews/store/{type}/{reviewable_id}', [ReviewController::class, 'store'])->name('reviews.store');
    Route::put('reviews/update/{review}', [ReviewController::class, 'update'])->name('reviews.update');
    Route::delete('reviews/destroy/{review}', [ReviewController::class, 'destroy'])->name('reviews.destroy');

    // REPORT ROUTE
    Route::post('reports', ReportController::class)->name('reports.store');

    // MESSAGING
    Route::get('conversations', [ConversationController::class, 'index'])->name('conversations.index');
    Route::post('conversations', [ConversationController::class, 'store'])->name('conversations.store');
    Route::get('conversations/{conversation}', [ConversationController::class, 'show'])->name('conversations.show');
    Route::post('conversations/{conversation}/read', [ConversationController::class, 'markAsRead'])->name('conversations.read');
    Route::post('conversations/{conversation}/messages', [MessageController::class, 'store'])->name('conversations.messages.store');
    Route::put('messages/{message}', [MessageController::class, 'update'])->name('messages.update');
    Route::delete('messages/{message}', [MessageController::class, 'destroy'])->name('messages.destroy');

    // COMMENTS ROUTES
    Route::post('comments/{commentable_type}/{commentable_id}', [CommentController::class, 'store'])
        ->whereNumber('commentable_id')
        ->name('comments.store');
    Route::put('comments/{comment}', [CommentController::class, 'update'])->name('comments.update');
    Route::delete('comments/{comment}', [CommentController::class, 'delete'])->name('comments.delete');
});

require __DIR__.'/settings.php';
require __DIR__.'/auth.php';
