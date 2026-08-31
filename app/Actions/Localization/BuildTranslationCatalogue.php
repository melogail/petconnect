<?php

namespace App\Actions\Localization;

use Illuminate\Filesystem\Filesystem;
use Illuminate\Support\Facades\App;

/**
 * The one locale's worth of UI strings the client is allowed to see.
 *
 * `lang/{locale}.json` and nothing else. This is the catalogue
 * Http\Middleware\HandleInertiaRequests ships as the `translations` prop, which
 * the Vue side reads through its own `t()` helper — the third role in the
 * locale layer .ai/rules/lang.md describes, alongside the whitelist, the writer
 * (Actions\Profiles\ApplyUserLocale) and the reader (Http\Middleware\SetLocale).
 *
 * ## Why it reads the file rather than asking the translator
 *
 * `Lang::getLoader()->load($locale, '*', '*')` looks like the framework-blessed
 * way to get the merged JSON catalogue, and it is wrong here. `FileLoader`
 * reduces over `jsonPaths` *and* `paths`, and in this application those are:
 *
 *   jsonPaths: lang/vendor/nova, advanced-nova-media-library (x2)
 *   paths:     vendor/laravel/framework/.../Translation/lang, lang
 *
 * so `load('en', '*', '*')` returns **1126** keys — this application's 633 plus
 * the 493 in `lang/vendor/nova/en.json`. That would push the entire back-office
 * catalogue into the props of every public page, and asymmetrically: Nova
 * publishes `en` only (the reason .ai/rules/lang.md pins Nova to `app.locale`),
 * so `ar` would come back with 668 and `en` with 1126. Reading the application's
 * own file keeps the two catalogues on opposite sides of the wire, which is
 * what the pin is for.
 *
 * The PHP group files under `lang/{locale}/` are excluded for the same kind of
 * reason and a stronger one: `validation.php`, `auth.php` and `passwords.php`
 * are server-side concerns that reach the client already rendered, inside the
 * `errors` prop. Sending the templates too would be duplicate weight with no
 * reader.
 *
 * ## The locale is filtered here, not just by the caller
 *
 * `$locale` names a file. SetLocale has already checked the cookie, the user
 * row and the session against `petconnect.locales.supported` before anything
 * reaches App::getLocale(), but this Action builds a path out of the value and
 * must not depend on a caller having done that — an unfiltered locale here is a
 * `lang/../../<anything>.json` read primitive. Same whitelist, same fallback to
 * `app.locale`, as ApplyUserLocale.
 *
 * ## No cache
 *
 * The file is static per deploy, but the read only happens on a full page visit
 * (see the `translations` once prop in HandleInertiaRequests), so it is one
 * ~40 KB `file_get_contents` plus a `json_decode` per browser navigation, not
 * per Inertia visit. A cache store round trip for 40 KB would not reliably beat
 * that, and `config:cache` does not cover `lang/`, so a cached copy would need
 * an invalidation story that nothing else in the locale layer has.
 *
 * Replaces the legacy App\Support\LocaleManager::translations(), which was the
 * same file read as a static with no whitelist filter of its own.
 */
class BuildTranslationCatalogue
{
    public function __construct(private readonly Filesystem $files) {}

    /**
     * Every UI string for one locale, keyed the way `__()` keys them.
     *
     * Two key styles are in here on purpose and both pass through untouched:
     * dotted keys (`nav.brand`, `notifications.*`) and English sentences used
     * as their own key. See .ai/rules/lang.md.
     *
     * @return array<string, string>
     */
    public function handle(?string $locale = null): array
    {
        $path = lang_path($this->supported($locale ?? App::getLocale()).'.json');

        if (! $this->files->isFile($path)) {
            return [];
        }

        /** @var array<string, string>|null $decoded */
        $decoded = json_decode($this->files->get($path), true);

        return is_array($decoded) ? $decoded : [];
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
}
