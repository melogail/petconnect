<?php

namespace App\Http\Middleware;

use App\Actions\Localization\BuildTranslationCatalogue;
use App\Http\Resources\User\AuthenticatedUserResource;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\App;
use Inertia\Inertia;
use Inertia\Middleware;
use Inertia\OnceProp;
use Laravel\Nova\Util;
use Symfony\Component\HttpFoundation\Response;

class HandleInertiaRequests extends Middleware
{
    /**
     * The root template that's loaded on the first page visit.
     *
     * @see https://inertiajs.com/server-side-setup#root-template
     *
     * @var string
     */
    protected $rootView = 'app';

    public function __construct(private readonly BuildTranslationCatalogue $buildTranslationCatalogue) {}

    /**
     * Clear anything a previous request shared, then share this request's props.
     *
     * `Inertia::share()` writes into the ResponseFactory **singleton**, and
     * nothing clears it. Under FPM that is invisible — one request, one process
     * — but it is a genuine cross-request leak under Octane, and it is already
     * observable in a single test that makes a Nova request and then an
     * application one.
     *
     * Nova is the concrete case. Laravel\Nova\Http\Middleware\
     * HandleInertiaRequests::share() registers `novaConfig`, `currentUser` and
     * `validLicense` closures on that same singleton, and nothing removes them
     * when the response goes out. They survive onto the next application page,
     * where `currentUser` resolves through `Nova::user()` on the `admin` guard
     * and `novaConfig` serialises the whole back-office configuration into the
     * props of a public page.
     *
     * It is not that this middleware sits out a Nova request — `config/nova.php`
     * puts the `web` group at the top of Nova's middleware stack, ahead of
     * Nova's own HandleInertiaRequests, so ours **does** run, and runs *first*.
     * Nova's share() therefore merges on top of ours rather than the other way
     * round, which is why the leak points one way. The props ours shares are
     * harmless there — `auth.user` is null, Nova authenticating on the `admin`
     * guard, and `locale.current` is `en` because SetLocale pins Nova to
     * `app.locale` (.ai/rules/lang.md) — and shareOnce() returns nothing at all
     * for a Nova request. That exclusion is a deliberate opt-out, not a
     * description of what the framework does on its own; read it that way before
     * changing it.
     *
     * Flushing first is the narrow fix: every prop this application needs is
     * re-registered by `parent::handle()` on the very next line, so nothing is
     * lost, and the application shares nothing from a service provider that a
     * flush here could drop.
     */
    public function handle(Request $request, Closure $next): Response
    {
        Inertia::flushShared();

        return parent::handle($request, $next);
    }

    /**
     * Determines the current asset version.
     *
     * @see https://inertiajs.com/asset-versioning
     */
    public function version(Request $request): ?string
    {
        return parent::version($request);
    }

    /**
     * The language every page needs to render itself.
     *
     * Three static values and no query. `current` is what SetLocale resolved
     * for this request; `direction` comes from `petconnect.locales.rtl`, so the
     * client never re-derives it from a hardcoded `=== 'ar'`; `supported` is
     * what the language picker renders.
     *
     * Shared rather than added per page because every layout needs `dir` on the
     * document element, and because the alternative — reading the cookie in JS —
     * would disagree with the server on the first request after a switch.
     *
     * This is the companion of the `translations` prop in shareOnce(), and
     * `current` is the authority: the catalogue's once key is derived from the
     * same App::getLocale(), so `locale.current` naming a language the client
     * has no catalogue for cannot happen. `current` is re-sent on every
     * response; `translations` only when the locale it was keyed to changes.
     *
     * Notifications are deliberately **not** shared. The legacy app put 20
     * notification rows plus an unread count into the props of every page
     * render; they now live behind `notifications.index`, so a page that never
     * opens the bell costs no notification query. See
     * Actions\Notifications\BuildNotificationInbox.
     *
     * @return array{current: string, direction: string, supported: list<string>}
     */
    protected function localeProps(): array
    {
        $current = App::getLocale();

        /** @var list<string> $supported */
        $supported = config('petconnect.locales.supported', ['en']);

        /** @var list<string> $rtl */
        $rtl = config('petconnect.locales.rtl', []);

        return [
            'current' => $current,
            'direction' => in_array($current, $rtl, true) ? 'rtl' : 'ltr',
            'supported' => $supported,
        ];
    }

    /**
     * The active locale's UI catalogue, sent once and remembered by the client.
     *
     * ## Why a once prop and not a regular one
     *
     * `lang/en.json` is 633 keys and `lang/ar.json` is 668. As an ordinary
     * shared prop that is ~47 KB (`en`) or ~82 KB (`ar`, escaped) added to
     * *every* Inertia response — every filter change on the feed, every page of
     * comments — to re-send a file that only changes on deploy. The legacy app
     * did exactly that.
     *
     * `Inertia::once()` is the v3 prop type for this. `PropsResolver` skips a
     * once prop whose key the client reports in `X-Inertia-Except-Once-Props`,
     * so the catalogue is resolved on the initial document request — the
     * exclusion is gated on `$this->isInertia`, so a full page visit always
     * gets it — and excluded from every Inertia visit afterwards, while the
     * client reuses the copy it already holds. The `onceProps` metadata still
     * rides along, which is how the client knows to keep it.
     *
     * ## The key carries the locale, which is what keeps it fresh
     *
     * `as("translations.{$locale}")` rather than the bare prop name. A once prop
     * is remembered per key, so on a language switch the client is holding
     * `translations.en`, the server offers `translations.ar`, the key does not
     * match and the Arabic catalogue is resolved and sent in the same response
     * that carries the new `locale.current` and `locale.direction`. The two
     * props cannot disagree about which language the page is in. A bare key
     * would have left the client rendering English strings inside an RTL
     * layout until the next hard refresh, with nothing to signal it.
     *
     * `translations.en` stops appearing in `onceProps` at that moment, so the
     * client forgets it; switching back resolves it again. Worst case is one
     * extra catalogue per switch, and a switch is a deliberate act.
     *
     * ## Nova gets nothing
     *
     * `config/nova.php` puts the `web` group in Nova's middleware stack, so
     * this middleware runs for Nova requests too and Nova's own
     * HandleInertiaRequests merges its props on top of these rather than
     * replacing them. SetLocale pins Nova to `app.locale` because
     * `lang/vendor/nova` publishes English only (.ai/rules/lang.md), so a
     * catalogue here would be dead weight in the back-office SPA and in every
     * `nova-api/*` response. Returning nothing for a Nova request is the same
     * question SetLocale asks, through the same `Util::isNovaRequest()` helper
     * .ai/rules/providers.md settled on — not a path prefix, which missed 78 of
     * Nova's 110 routes.
     *
     * @return array<string, OnceProp>
     */
    public function shareOnce(Request $request): array
    {
        if (Util::isNovaRequest($request)) {
            return [];
        }

        $locale = App::getLocale();

        return [
            'translations' => Inertia::once(
                fn (): array => $this->buildTranslationCatalogue->handle($locale),
            )->as("translations.{$locale}"),
        ];
    }

    /**
     * Define the props that are shared by default.
     *
     * ## `auth.user` goes through a Resource, and that is the point
     *
     * This used to be `'user' => $request->user()`, which let the model's own
     * toArray() decide the payload — i.e. every column except User's four
     * `#[Hidden]` ones. That put the viewer's `address`, `lat`, `lng`, `phone`,
     * `media_directory_name`, `two_factor_confirmed_at` and `last_seen_at` into
     * the props of every page in the application, the public feed included.
     * Nothing leaked between users; it went into every browser cache, history
     * entry and screen share the viewer's own pages touch.
     *
     * Http\Resources\User\AuthenticatedUserResource is the payload, and its
     * docblock owns the key list and what is deliberately not on it.
     *
     * The ternary is load bearing: `auth.user` is **null for a guest** on every
     * public page, and the resource reads properties off the model, so it must
     * never be constructed around null. See .ai/rules/types.md — the client type
     * says non-nullable and is wrong on purpose.
     *
     * @see https://inertiajs.com/shared-data
     *
     * @return array<string, mixed>
     */
    public function share(Request $request): array
    {
        $user = $request->user();

        return [
            ...parent::share($request),
            'name' => config('app.name'),
            'auth' => [
                'user' => $user === null ? null : AuthenticatedUserResource::make($user),
            ],
            'sidebarOpen' => ! $request->hasCookie('sidebar_state') || $request->cookie('sidebar_state') === 'true',
            'locale' => $this->localeProps(),
        ];
    }
}
