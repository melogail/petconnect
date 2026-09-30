<?php

use App\Http\Controllers\Api\V1\AccountController;
use App\Http\Controllers\Api\V1\Auth\EmailVerificationNotificationController;
use App\Http\Controllers\Api\V1\Auth\LoginController;
use App\Http\Controllers\Api\V1\Auth\LogoutController;
use App\Http\Controllers\Api\V1\Auth\PasswordResetLinkController;
use App\Http\Controllers\Api\V1\Auth\RegisterController;
use App\Http\Controllers\Api\V1\BadgeController;
use App\Http\Controllers\Api\V1\CategoryController;
use App\Http\Controllers\Api\V1\CommentController;
use App\Http\Controllers\Api\V1\ConversationController;
use App\Http\Controllers\Api\V1\MessageController;
use App\Http\Controllers\Api\V1\NotificationController;
use App\Http\Controllers\Api\V1\OptionController;
use App\Http\Controllers\Api\V1\PasswordController;
use App\Http\Controllers\Api\V1\PetController;
use App\Http\Controllers\Api\V1\ProfileController;
use App\Http\Controllers\Api\V1\ReportController;
use App\Http\Controllers\Api\V1\ReviewController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| The mobile API
|--------------------------------------------------------------------------
|
| Everything under `/api/v1`, consumed by the SniffPal Flutter app and
| authenticated by a Sanctum bearer token. The `api` middleware group
| (bootstrap/app.php) makes `sanctum` the default guard for the whole file,
| so the public reads below know who is asking whenever a token is attached
| and `auth:sanctum` on the guarded groups is a hard requirement.
|
| This file mirrors routes/web.php one to one — same Form Requests, same
| Actions, same policies, same limiters — and differs only in the response:
| JSON instead of an Inertia page or a redirect. Read the web file for why
| each vertical is shaped the way it is; only what is *different* here is
| explained here.
|
| The verification contract: every write that needs a verified account sits
| behind `verified`, exactly as on the web. For a JSON request the framework's
| EnsureEmailIsVerified aborts 403 with "Your email address is not verified."
| The app does not parse that message — it reads `is_verified` from
| `api.v1.me.show` and gates the publish, comment, review and message controls
| itself; the 403 is the backstop.
|
| Every mutating route carries a named limiter (.ai/rules/routes.md). The two
| that had none to borrow — `verification-notifications` and
| `password-changes` — are registered in AppServiceProvider beside the rest.
|
| Every route is named `api.v1.*`, and no controller method is registered at
| two URIs, the same discipline as the web file even though Wayfinder never
| reads this one.
|
*/

Route::prefix('v1')->name('api.v1.')->group(function (): void {
    /*
    |----------------------------------------------------------------------
    | Public reads
    |----------------------------------------------------------------------
    |
    | Reachable without a token, with a token they carry the viewer. Same set
    | as the public half of routes/web.php plus `options` and `categories`,
    | which the web ships as Inertia props and a native client fetches once.
    |
    */

    Route::get('options', [OptionController::class, 'index'])->name('options');
    Route::get('categories', [CategoryController::class, 'index'])->name('categories.index');

    Route::get('pets', [PetController::class, 'index'])->name('pets.index');
    Route::get('pets/{pet}', [PetController::class, 'show'])->whereNumber('pet')->name('pets.show');

    Route::whereNumber('user')->prefix('profiles')->name('profiles.')->group(function (): void {
        Route::get('{user}', [ProfileController::class, 'show'])->name('show');
        Route::get('{user}/pets', [ProfileController::class, 'pets'])->name('pets');
        Route::get('{user}/reviews', [ProfileController::class, 'reviews'])->name('reviews');
    });

    Route::prefix('comments')->name('comments.')->group(function (): void {
        Route::get('{comment}/replies', [CommentController::class, 'replies'])
            ->whereNumber('comment')
            ->name('replies');

        Route::get('{commentable_type}/{commentable_id}', [CommentController::class, 'index'])
            ->whereNumber('commentable_id')
            ->name('index');
    });

    Route::get('reviews/{reviewable_type}/{reviewable_id}', [ReviewController::class, 'index'])
        ->whereNumber('reviewable_id')
        ->name('reviews.index');

    /*
    |----------------------------------------------------------------------
    | Auth
    |----------------------------------------------------------------------
    |
    | Fortify owns the browser flows and does not issue tokens, so these are
    | the app's own. `register` and `login` spend the same limiter buckets
    | the browser forms spend (`registrations` is IP-keyed; `login` is
    | Fortify's email|ip limiter), so switching clients buys no allowance.
    |
    */

    Route::prefix('auth')->name('auth.')->group(function (): void {
        Route::post('register', [RegisterController::class, 'store'])
            ->middleware('throttle:registrations')
            ->name('register');

        Route::post('login', [LoginController::class, 'store'])
            ->middleware('throttle:login')
            ->name('login');

        Route::post('forgot-password', [PasswordResetLinkController::class, 'store'])
            ->middleware('throttle:password-reset-links')
            ->name('password.email');

        Route::middleware('auth:sanctum')->group(function (): void {
            Route::post('logout', [LogoutController::class, 'destroy'])
                ->middleware('throttle:content-edits')
                ->name('logout');

            Route::post('email/verification-notification', [EmailVerificationNotificationController::class, 'store'])
                ->middleware('throttle:verification-notifications')
                ->name('verification.send');
        });
    });

    /*
    |----------------------------------------------------------------------
    | The account, signed in but not necessarily verified
    |----------------------------------------------------------------------
    |
    | Mirrors routes/settings.php: reading and editing the profile is `auth`
    | only, so a freshly registered user can fix a typo in their name while
    | they wait for the verification mail. Notifications and the tab badges
    | are here for the same reason — the inbox is the user's own.
    |
    */

    Route::middleware('auth:sanctum')->group(function (): void {
        Route::get('me', [AccountController::class, 'show'])->name('me.show');

        Route::patch('me', [AccountController::class, 'update'])
            ->middleware('throttle:profile-updates')
            ->name('me.update');

        Route::get('me/badges', [BadgeController::class, 'show'])->name('me.badges');

        Route::prefix('notifications')->name('notifications.')->group(function (): void {
            Route::get('/', [NotificationController::class, 'index'])->name('index');

            Route::middleware('throttle:inbox-actions')->group(function (): void {
                Route::post('read-all', [NotificationController::class, 'markAllAsRead'])->name('read-all');
                Route::delete('/', [NotificationController::class, 'destroyAll'])->name('destroy-all');
                Route::post('{notification}/read', [NotificationController::class, 'markAsRead'])->name('read');
            });
        });
    });

    /*
    |----------------------------------------------------------------------
    | Verified writes
    |----------------------------------------------------------------------
    */

    Route::middleware(['auth:sanctum', 'verified'])->group(function (): void {
        Route::put('me/password', [PasswordController::class, 'update'])
            ->middleware('throttle:password-changes')
            ->name('me.password.update');

        Route::delete('me', [AccountController::class, 'destroy'])
            ->middleware('throttle:account-deletions')
            ->name('me.destroy');

        // Listings
        Route::post('pets', [PetController::class, 'store'])
            ->middleware('throttle:pet-listings')
            ->name('pets.store');

        Route::whereNumber('pet')->group(function (): void {
            Route::put('pets/{pet}', [PetController::class, 'update'])
                ->middleware('throttle:pet-listing-edits')
                ->name('pets.update');

            Route::delete('pets/{pet}', [PetController::class, 'destroy'])
                ->middleware('throttle:content-edits')
                ->name('pets.destroy');

            Route::patch('pets/{pet}/status', [PetController::class, 'toggleStatus'])
                ->middleware('throttle:content-edits')
                ->name('pets.status.toggle');

            Route::post('pets/{pet}/like', [PetController::class, 'toggleLike'])
                ->middleware('throttle:pet-likes')
                ->name('pets.like');
        });

        // Profiles
        Route::post('profiles/{user}/like', [ProfileController::class, 'toggleLike'])
            ->whereNumber('user')
            ->middleware('throttle:profile-likes')
            ->name('profiles.like');

        // Comments
        Route::prefix('comments')->name('comments.')->group(function (): void {
            Route::post('{comment}/like', [CommentController::class, 'toggleLike'])
                ->whereNumber('comment')
                ->middleware('throttle:comment-likes')
                ->name('like');

            Route::post('{commentable_type}/{commentable_id}', [CommentController::class, 'store'])
                ->whereNumber('commentable_id')
                ->middleware('throttle:comments')
                ->name('store');

            Route::middleware('throttle:content-edits')
                ->whereNumber('comment')
                ->group(function (): void {
                    Route::put('{comment}', [CommentController::class, 'update'])->name('update');
                    Route::delete('{comment}', [CommentController::class, 'destroy'])->name('destroy');
                });
        });

        // Reviews
        Route::prefix('reviews')->name('reviews.')->group(function (): void {
            Route::post('{reviewable_type}/{reviewable_id}', [ReviewController::class, 'store'])
                ->whereNumber('reviewable_id')
                ->middleware('throttle:reviews')
                ->name('store');

            Route::middleware('throttle:content-edits')
                ->whereNumber('review')
                ->group(function (): void {
                    Route::put('{review}', [ReviewController::class, 'update'])->name('update');
                    Route::delete('{review}', [ReviewController::class, 'destroy'])->name('destroy');
                });
        });

        // Reports
        Route::post('reports/{reportable_type}/{reportable_id}', [ReportController::class, 'store'])
            ->whereNumber('reportable_id')
            ->middleware('throttle:reports')
            ->name('reports.store');

        // Messaging
        Route::prefix('conversations')->name('conversations.')->group(function (): void {
            Route::get('/', [ConversationController::class, 'index'])->name('index');

            Route::post('/', [ConversationController::class, 'store'])
                ->middleware('throttle:conversations')
                ->name('store');

            Route::whereNumber('conversation')->group(function (): void {
                Route::get('{conversation}', [ConversationController::class, 'show'])->name('show');

                Route::post('{conversation}/read', [ConversationController::class, 'markAsRead'])
                    ->middleware('throttle:inbox-actions')
                    ->name('read');

                Route::get('{conversation}/messages', [MessageController::class, 'index'])->name('messages.index');

                Route::post('{conversation}/messages', [MessageController::class, 'store'])
                    ->middleware('throttle:messages')
                    ->name('messages.store');
            });
        });

        Route::prefix('messages')->name('messages.')->whereNumber('message')->group(function (): void {
            Route::middleware('throttle:content-edits')->group(function (): void {
                Route::put('{message}', [MessageController::class, 'update'])->name('update');
                Route::delete('{message}', [MessageController::class, 'destroy'])->name('destroy');
                Route::post('{message}/pin', [MessageController::class, 'togglePin'])->name('pin');
            });
        });
    });
});
