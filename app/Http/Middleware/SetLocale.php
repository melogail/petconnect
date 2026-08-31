<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\App;
use Laravel\Nova\Util;
use Symfony\Component\HttpFoundation\Response;

/**
 * Decide which language this request is rendered in.
 *
 * Read-only by design: it picks a locale from what is already recorded and
 * hands it to App::setLocale(). Nothing here writes a cookie, a session key or
 * a user row — that is Actions\Profiles\ApplyUserLocale's, called from
 * LocaleController and from the profile update flow. One writer, one reader.
 *
 * ## Precedence, and why the cookie wins over the user row
 *
 * cookie -> user -> session -> `app.locale`.
 *
 * The cookie is first because it is the most recent *explicit* act: switching
 * language writes the cookie and the user row together, so they only disagree
 * when a signed-in user has since switched on a device whose cookie says
 * otherwise, and honouring the device is what somebody reading in a shared
 * browser expects. The user row is second so a signed-in account carries its
 * language to a new browser on the first request, before any cookie exists.
 * The session is a fallback for a client that refuses cookies.
 *
 * Every candidate is checked against `petconnect.locales.supported`, so a
 * hand-edited cookie cannot make App::setLocale() take an arbitrary string —
 * which would leave every `__()` on the page returning its own key.
 *
 * ## Nova is pinned to English, and the check is Nova's own
 *
 * `lang/vendor/nova` publishes `en` only, so an admin browsing Nova in `ar`
 * would get Nova's own chrome in English and this application's model labels in
 * Arabic — half a translation, which is worse than none. Pinning it is the
 * legacy behaviour and is kept deliberately; revisit it when a Nova `ar`
 * translation is published, not before.
 *
 * The legacy middleware matched Nova with
 * `$request->is('nova', 'nova/*', 'nova-api', ...)`. That is close, but it is
 * the same hand-rolled prefix list that .ai/rules/providers.md records as
 * having missed 78 of Nova's 110 routes when AppServiceProvider tried it, so
 * this asks Nova's own matcher instead — one helper, tracked by the package,
 * shared with the lazy-loading exemption.
 */
class SetLocale
{
    /**
     * @param  Closure(Request): Response  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        App::setLocale($this->isNovaRequest($request)
            ? (string) config('app.locale', 'en')
            : $this->resolveLocale($request));

        return $next($request);
    }

    /**
     * The first recorded preference that names a supported language.
     */
    protected function resolveLocale(Request $request): string
    {
        /** @var list<string> $supported */
        $supported = config('petconnect.locales.supported', ['en']);

        $candidates = [
            $request->cookie($this->cookieName()),
            $request->user()?->locale,
            $request->hasSession() ? $request->session()->get($this->cookieName()) : null,
        ];

        foreach ($candidates as $candidate) {
            if (is_string($candidate) && in_array($candidate, $supported, true)) {
                return $candidate;
            }
        }

        return (string) config('app.locale', 'en');
    }

    protected function cookieName(): string
    {
        return (string) config('petconnect.locales.cookie', 'locale');
    }

    protected function isNovaRequest(Request $request): bool
    {
        return Util::isNovaRequest($request);
    }
}
