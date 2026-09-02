<?php

use App\Models\Admin;
use Illuminate\Testing\TestResponse;
use Inertia\Middleware;
use Inertia\Testing\AssertableInertia;

/**
 * Make an Inertia visit, optionally telling the server which once props the
 * client already holds — which is what a real client does after its first
 * document request.
 *
 * @param  list<string>  $heldOnceProps
 */
function inertiaVisit(string $url, array $heldOnceProps = [], array $cookies = []): TestResponse
{
    $test = test()->withHeaders(array_filter([
        'X-Inertia' => 'true',
        'X-Inertia-Version' => (string) app(Middleware::class)->version(request()),
        'X-Inertia-Except-Once-Props' => implode(',', $heldOnceProps) ?: null,
    ]));

    foreach ($cookies as $name => $value) {
        $test = $test->withUnencryptedCookie($name, $value);
    }

    return $test->get($url);
}

/**
 * The catalogue is the one prop in this application shipped with
 * `Inertia::once()`, and the reason is size: `lang/en.json` is 633 keys (~47 KB)
 * and `lang/ar.json` 668 (~82 KB escaped). As an ordinary shared prop that
 * rides on every filter change and every page of comments, which is what the
 * legacy app did.
 */
test('sends the active language catalogue on a full page visit', function () {
    $response = $this->get(route('home'))->assertOk();

    expect($response->inertiaProps('translations'))
        ->toHaveKey('nav.brand')
        ->and($response->inertiaProps('translations')['nav.brand'])->toBe('PetConnect')
        ->and($response->inertiaProps('locale')['current'])->toBe('en');
});

/**
 * The once key carries the locale — `as("translations.{$locale}")` — and that is
 * what keeps the catalogue and `locale.current` from disagreeing. A once prop is
 * remembered by key, so on a switch the client is holding `translations.en`, the
 * server offers `translations.ar`, the keys do not match and the Arabic
 * catalogue is resolved in the same response that carries the new
 * `locale.current`. Under a bare `translations` key the client would keep
 * rendering English strings inside an RTL layout until a hard refresh, with
 * nothing to signal it.
 */
test('keys the catalogue by language, so a switch resends it in the same response as the new locale', function () {
    $response = inertiaVisit(
        route('home'),
        heldOnceProps: ['translations.en'],
        cookies: ['locale' => 'ar'],
    )->assertOk();

    expect($response->json('onceProps'))->toHaveKey('translations.ar')
        ->and($response->json('onceProps'))->not->toHaveKey('translations.en')
        ->and($response->json('props.translations'))->toHaveKey('nav.brand')
        ->and($response->json('props.translations')['nav.brand'])->toBe('بيت كونكت')
        ->and($response->json('props.locale.current'))->toBe('ar');
});

/**
 * The saving itself: once the client reports it holds the key, the catalogue is
 * not resolved again. The `onceProps` metadata still rides along, which is how
 * the client knows to keep the copy it has rather than forgetting it.
 */
test('omits the catalogue from a visit whose client already holds it', function () {
    $response = inertiaVisit(route('home'), heldOnceProps: ['translations.en'])->assertOk();

    expect($response->json('props'))->not->toHaveKey('translations')
        ->and($response->json('onceProps'))->toHaveKey('translations.en')
        ->and($response->json('props.locale.current'))->toBe('en');
});

/**
 * Nova gets nothing. `config/nova.php` puts the `web` group at the top of Nova's
 * middleware stack, so this middleware runs for Nova requests too, and SetLocale
 * pins Nova to `app.locale` because `lang/vendor/nova` publishes English only
 * (.ai/rules/lang.md). A catalogue here would be dead weight in the back-office
 * SPA and in every `nova-api/*` response.
 */
test('sends no catalogue to the back office', function () {
    $response = $this->actingAs(Admin::factory()->create(), 'admin')
        ->get('/nova/dashboards/main')
        ->assertOk();

    expect($response->inertiaProps())->not->toHaveKey('translations');
});

/**
 * Nova and the application are two Inertia apps in one process sharing one
 * ResponseFactory singleton, and nothing clears it between requests. Nova's
 * `HandleInertiaRequests::share()` registers `novaConfig`, `currentUser` and
 * `validLicense` closures on it; left there, `novaConfig` would be resolved
 * while rendering the *next* application page and serialise the whole
 * back-office configuration into the props of a page a guest can read.
 *
 * `handle()` calls `Inertia::flushShared()` before `parent::handle()`
 * re-registers this application's own props, which is the fix. Removing that one
 * line breaks nothing else in the suite, so the leak is asserted here: a public
 * page rendered after a Nova request carries the shared props this application
 * declares and no others.
 *
 * `help` is the page to measure it on because it declares no props of its own
 * (see tests/Feature/HelpAndSupportTest), so anything in the payload beyond the
 * shared set came from somewhere it should not have.
 */
test('leaks no back office prop onto a public page rendered after a nova request', function () {
    $this->withoutVite();

    $this->actingAs(Admin::factory()->create(), 'admin')
        ->get('/nova/dashboards/main')
        ->assertOk();

    $this->app['auth']->forgetGuards();

    $response = $this->get(route('help'))->assertOk();

    expect(array_keys($response->inertiaProps()))
        ->toEqualCanonicalizing(['errors', 'name', 'auth', 'sidebarOpen', 'locale', 'translations']);
});

/**
 * The layouts put `dir` on the document element from this prop rather than
 * re-deriving it from a hardcoded `=== 'ar'`, so the direction has to travel
 * with the language rather than being inferred beside it.
 */
test('ships the reading direction alongside the language', function (string $locale, string $direction) {
    $this->withUnencryptedCookie('locale', $locale)
        ->get(route('home'))
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->where('locale.current', $locale)
            ->where('locale.direction', $direction)
            ->where('locale.supported', ['en', 'ar'])
            ->etc());
})->with([
    'english reads left to right' => ['en', 'ltr'],
    'arabic reads right to left' => ['ar', 'rtl'],
]);
