<?php

use Inertia\Testing\AssertableInertia;

/**
 * The only two pages in the application with no controller behind them:
 * `Route::inertia()` and nothing else, the same as `dashboard` and
 * `appearance.edit`.
 *
 * Two things are worth asserting and neither is visible from a status code.
 *
 * **They are public.** Both were public before and "how does this work" and
 * "how do I reach a human" are exactly the questions somebody without an
 * account has. Dropping them into the `auth` group is a one-line edit that no
 * other test would notice, and `profile.show` has already been broken that way
 * once (see the routes/web.php docblock).
 *
 * **They carry no props.** `Route::inertia()` stores a third argument as route
 * *defaults*, which `route:cache` serialises, so a `config()` call added there
 * would freeze into the cached route file and go stale — that needs a
 * controller or a shared prop instead. The assertion below is therefore that
 * the payload holds the middleware's shared props and nothing else; a prop
 * added to the route declaration fails it.
 *
 * Note for whoever runs `route:list --except-vendor` and cannot find these:
 * `Route::inertia()` registers `\Inertia\Controller` as the action, so the
 * filter drops them. `dashboard` and `appearance.edit` are hidden identically.
 * That is the filter, not a missing route.
 *
 * @todo Drop `withoutVite()` and `shouldExist: false` once
 *   `resources/js/pages/Help.vue` and `resources/js/pages/Support.vue` are in
 *   the tree. Neither existed when this was written, so the component name is
 *   asserted without the file-existence check that
 *   `inertia.testing.ensure_pages_exist` would otherwise apply.
 */
test('a guest reads the page and gets no props beyond the shared ones', function (string $route, string $component) {
    $this->withoutVite();

    $response = $this->get(route($route))
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $page) => $page->component($component, shouldExist: false));

    expect(array_keys($response->inertiaProps()))
        ->toEqualCanonicalizing(['errors', 'name', 'auth', 'sidebarOpen', 'locale']);
})->with([
    'help' => ['help', 'Help'],
    'support' => ['support', 'Support'],
]);
