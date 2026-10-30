<?php

use App\Http\Controllers\Web\DocsController;
use App\Http\Middleware\DisallowRobots;
use Illuminate\Support\Facades\Route;
use Laravel\Nova\Http\Middleware\Authenticate as AuthenticateAdmin;

/*
|--------------------------------------------------------------------------
| Internal documentation (docs.<app host>)
|--------------------------------------------------------------------------
|
| Read-only, so no limiter (.ai/rules/routes.md covers mutating routes only).
| Nova's Authenticate middleware checks the `admin` guard and sends a guest to
| the Nova login page, which lives on `nova.domain`; the admin session reaches
| this host only because SESSION_DOMAIN is the parent domain. DisallowRobots wraps the
| whole group so the login redirect is noindex as well as the pages.
|
| The `nova*` carve-out matters when `nova.domain` is unset (the test suite):
| Nova's routes then answer on every host and the catch-all would otherwise
| swallow them here, looping the guest redirect to /nova/login.
|
*/

Route::domain(config('docs.domain'))
    ->middleware(DisallowRobots::class)
    ->group(function (): void {
        /*
         * Nova's guest redirect is the bare path `/login`, so on this host it
         * would land in the catch-all and loop. Forward it to the Nova host;
         * the intended URL is already in the shared session.
         */
        Route::get('login', fn () => redirect()->route('nova.pages.login'))->name('docs.login');

        Route::middleware(AuthenticateAdmin::class)
            ->get('{path?}', DocsController::class)
            ->where('path', '^(?!nova(?:$|[/-])).*$')
            ->name('docs.show');
    });
