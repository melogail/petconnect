<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Drop an intended URL that points at another host before the public site's
 * auth flows redirect to it.
 *
 * `SESSION_DOMAIN` is the parent domain so one admin session reaches Nova and
 * the docs host, which means the guest redirect on `nova.<host>` stores its
 * `url.intended` in the same session the public site reads. Every Fortify,
 * passkey and social-login response then does `redirect()->intended()`, and
 * for an Inertia XHR a 302 to another origin is a CORS failure the client
 * reports as a network error: the member is logged in, the form looks dead.
 *
 * Only the public host is scrubbed. Nova answers its login with a JSON
 * `redirect` the client follows by full navigation, and the docs host relies
 * on exactly that to send an admin back after the Nova login.
 */
class ForgetForeignIntendedUrl
{
    /**
     * Handle an incoming request.
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $appHost      = parse_url((string) config('app.url'), PHP_URL_HOST);
        $intendedHost = parse_url((string) $request->session()->get('url.intended', ''), PHP_URL_HOST);

        if ($request->getHost() === $appHost && $intendedHost !== null && $intendedHost !== $appHost) {
            $request->session()->forget('url.intended');
        }

        return $next($request);
    }
}
