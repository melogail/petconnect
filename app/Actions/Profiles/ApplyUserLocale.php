<?php

namespace App\Actions\Profiles;

use App\Models\User;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Cookie;
use Illuminate\Support\Facades\Session;

/**
 * Make a locale the active one, and remember it everywhere it has to be
 * remembered.
 *
 * The single place a locale is *chosen*. SetLocale only reads the record this
 * Action writes; nothing else calls App::setLocale() or queues the locale
 * cookie.
 *
 * ## Why three stores, not one
 *
 * A guest has no row to write to, so the cookie is the only thing that survives
 * their next visit. The session covers the rest of the current visit even if
 * the client drops cookies. The `users.locale` column is what
 * User::preferredLocale() reads, which is what queued mail and notifications
 * are rendered in — a user who switched to Arabic in the browser and then gets
 * an English verification email has not really switched. Writing all three is
 * cheap and keeps the three readers in agreement.
 *
 * The user write goes through forceFill() rather than update(): `locale` is in
 * User's #[Fillable] today, but this Action must keep working if it is ever
 * removed, and it is writing a value it has already validated against the
 * whitelist itself.
 *
 * ## It is defensive about the value on purpose
 *
 * Callers already validate — UpdateLocaleRequest and the profile form's
 * `locale` rule both use `Rule::in(config('petconnect.locales.supported'))` —
 * but this is also called from a pipeline step reading a validated bag, and an
 * unsupported locale reaching App::setLocale() means every `__()` on the page
 * silently returns its key. Falling back to `app.locale` makes the worst case a
 * page in English rather than a page of raw translation keys.
 *
 * Returns the locale actually applied, which is not always the one asked for.
 *
 * Replaces the legacy App\Support\LocaleManager, a static utility class with
 * the same three writes. It is an Action rather than a static because it has
 * two callers — Http\Controllers\Web\LocaleController and
 * Pipelines\Profiles\UpdateProfile\ApplyLocalePreference — which is the bar
 * .ai/rules/pipelines.md sets for extracting one.
 */
class ApplyUserLocale
{
    public function handle(string $locale, ?User $user = null): string
    {
        $locale = $this->supported($locale);

        App::setLocale($locale);

        Session::put($this->cookieName(), $locale);

        Cookie::queue(Cookie::make(
            $this->cookieName(),
            $locale,
            (int) config('petconnect.locales.cookie_minutes', 525600),
        ));

        if ($user !== null && $user->locale !== $locale) {
            $user->forceFill(['locale' => $locale])->save();
        }

        return $locale;
    }

    /**
     * The locale if it is on the whitelist, the application default otherwise.
     */
    protected function supported(string $locale): string
    {
        /** @var list<string> $supported */
        $supported = config('petconnect.locales.supported', ['en']);

        return in_array($locale, $supported, true)
            ? $locale
            : (string) config('app.locale', 'en');
    }

    protected function cookieName(): string
    {
        return (string) config('petconnect.locales.cookie', 'locale');
    }
}
