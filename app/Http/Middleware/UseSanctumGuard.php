<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

/**
 * Make `sanctum` the default guard for the whole `api` group.
 *
 * The public API reads (`api.v1.pets.index`, `api.v1.pets.show`, the
 * comment and review threads, a profile) carry no `auth` middleware because a
 * signed-out mobile visitor has to be able to browse — the same decision
 * `routes/web.php` records for the discovery feed. Those routes still want to
 * know who is asking when a bearer token *is* attached: `PetCardResource`
 * emits `is_liked` and `is_owner`, `ListHomeFeedPets` takes a `viewer`, and
 * every policy `view` method accepts a `?User`.
 *
 * `$request->user()` and `Gate::authorize()` both resolve through the default
 * guard, and the default is `web` — a session guard that has nothing to read
 * on a stateless API request and so always answers null. `Auth::shouldUse()`
 * moves the default to `sanctum` for the rest of this request, so the token
 * bearer is the viewer on a public read and `auth:sanctum` on the guarded
 * routes is a hard requirement rather than a change of guard. Sanctum's own
 * guard falls back to the web session for first-party SPAs, so a browser
 * session carried into `/api/*` keeps working too.
 */
class UseSanctumGuard
{
    public function handle(Request $request, Closure $next): Response
    {
        Auth::shouldUse('sanctum');

        return $next($request);
    }
}
