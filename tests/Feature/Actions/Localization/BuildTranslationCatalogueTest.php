<?php

use App\Actions\Localization\BuildTranslationCatalogue;
use Illuminate\Support\Facades\App;

/**
 * The catalogue is `lang/{locale}.json` and nothing else, which is the whole
 * point of the Action existing rather than a `Lang::getLoader()->load()` call:
 * the loader reduces over `jsonPaths` as well as `paths`, and in this
 * application `jsonPaths` includes `lang/vendor/nova`. `lang/vendor/nova/en.json`
 * carries 493 keys this application never renders, and it publishes English
 * only — so the loader would put the whole back-office catalogue into the props
 * of every public page, and asymmetrically, `en` arriving heavier than `ar`.
 *
 * Asserted by absence of a Nova-only key rather than by a count, because a
 * count moves whenever somebody adds a string.
 */
test('ships the application catalogue for a locale without the nova vendor one', function (string $locale, string $brand) {
    $catalogue = app(BuildTranslationCatalogue::class)->handle($locale);

    expect($catalogue)->toHaveKey('nav.brand')
        ->and($catalogue['nav.brand'])->toBe($brand)
        ->and($catalogue)->not->toHaveKey('Sorry! You are not authorized to perform this action.');
})->with([
    'english' => ['en', 'PetConnect'],
    'arabic' => ['ar', 'بيت كونكت'],
]);

/**
 * The PHP group files under `lang/{locale}/` stay server side. `validation.php`,
 * `auth.php` and `passwords.php` reach the client already rendered inside the
 * `errors` prop, so shipping the templates as well would be duplicate weight
 * with no reader.
 */
test('leaves the server side group files out of the catalogue', function () {
    $catalogue = app(BuildTranslationCatalogue::class)->handle('en');

    expect($catalogue)->not->toHaveKey('validation.required')
        ->and($catalogue)->not->toHaveKey('auth.failed')
        ->and($catalogue)->not->toHaveKey('passwords.reset');
});

/**
 * `$locale` is interpolated into a filesystem path, so an unfiltered value here
 * is a read primitive over anything reachable from `lang/`. The Action filters
 * against `petconnect.locales.supported` itself rather than trusting that
 * SetLocale already did — a caller passing `App::getLocale()` is the usual case,
 * not the guaranteed one.
 */
test('falls back to the application language rather than reading a path the whitelist does not name', function (string $locale) {
    config(['app.locale' => 'en']);

    $catalogue = app(BuildTranslationCatalogue::class)->handle($locale);

    expect($catalogue['nav.brand'])->toBe('PetConnect');
})->with([
    'an unsupported language' => ['fr'],
    'a traversal out of lang' => ['../../composer'],
    'a traversal back into lang' => ['ar/../en'],
    'a nested vendor catalogue' => ['vendor/nova/en'],
]);

test('reads the language the application is currently in when it is given none', function () {
    App::setLocale('ar');

    expect(app(BuildTranslationCatalogue::class)->handle()['nav.brand'])->toBe('بيت كونكت');
});

/**
 * A locale can be whitelisted before its file lands. The client then renders
 * every string through its own fallback rather than the page failing, which is
 * the cheaper of the two failures.
 */
test('returns an empty catalogue for a supported language with no file yet', function () {
    config(['petconnect.locales.supported' => ['en', 'fr']]);

    expect(app(BuildTranslationCatalogue::class)->handle('fr'))->toBe([]);
});
