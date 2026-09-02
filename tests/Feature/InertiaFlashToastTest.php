<?php

use App\Models\Pet;
use App\Models\User;
use Inertia\Testing\AssertableInertia;

/**
 * The toast contract, which nothing else in the suite touches.
 *
 * Every write in the application ends with
 * `Inertia::flash('toast', ['type' => ..., 'message' => ...])` and a redirect —
 * eighteen call sites across nine controllers — and `resources/js/lib/flashToast.ts`
 * is the single reader: it takes `flash.toast` off the `flash` router event and
 * calls `toast[data.type](data.message)`. So the key is `toast`, the payload is
 * `{type, message}`, and `type` has to be a method vue-sonner exposes
 * (`resources/js/types/ui.ts` narrows it to success/info/warning/error). A typo
 * in any of the three is silent on both sides: the server still redirects, the
 * page still renders, and the user simply never learns their write landed.
 *
 * Flash data is not a shared prop and does not survive into history state, so
 * it cannot be asserted the way `locale` or `auth` are — it lives in the
 * session under Inertia's own key until the response after the redirect picks
 * it up. That is why the tests below follow the redirect rather than reading
 * the props of the write itself.
 *
 * One representative write is enough for the wiring; a case per controller
 * would be eighteen tests detecting one defect.
 */
test('a write flashes a toast that reaches the page it redirects to', function () {
    $author = User::factory()->create();
    $pet = Pet::factory()->create();

    $this->actingAs($author)
        ->from(route('pets.show', $pet))
        ->post(route('comments.store', ['commentable_type' => 'pet', 'commentable_id' => $pet->getKey()]), [
            'content' => 'Is she still available?',
        ])
        ->assertRedirect(route('pets.show', $pet))
        ->assertInertiaFlash('toast', ['type' => 'success', 'message' => 'Comment posted.']);

    $this->actingAs($author)
        ->get(route('pets.show', $pet))
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->hasFlash('toast', ['type' => 'success', 'message' => 'Comment posted.']));
});

/**
 * A toast is an announcement about one write, so it has to be spent by the page
 * that shows it. Flashed rather than shared precisely so a reload does not
 * re-announce a comment posted five minutes ago.
 */
test('the toast is gone by the following request, so a write is announced once', function () {
    $author = User::factory()->create();
    $pet = Pet::factory()->create();

    $this->actingAs($author)
        ->from(route('pets.show', $pet))
        ->post(route('comments.store', ['commentable_type' => 'pet', 'commentable_id' => $pet->getKey()]), [
            'content' => 'Is she still available?',
        ]);

    $this->actingAs($author)->get(route('pets.show', $pet))->assertOk();

    $this->actingAs($author)
        ->get(route('pets.show', $pet))
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $page) => $page->missingFlash('toast'));
});

/**
 * A failed write announces nothing. The client shows a toast for whatever
 * arrives under the key, so a toast flashed before validation ran would tell
 * somebody their comment posted while the form was still holding an error.
 */
test('a rejected write flashes no toast', function () {
    $author = User::factory()->create();
    $pet = Pet::factory()->create();

    $this->actingAs($author)
        ->from(route('pets.show', $pet))
        ->post(route('comments.store', ['commentable_type' => 'pet', 'commentable_id' => $pet->getKey()]), [
            'content' => '',
        ])
        ->assertInvalid(['content'])
        ->assertInertiaFlashMissing('toast');
});

/**
 * The message is a `__()` call resolved while the request is still running, so
 * it is rendered in the language SetLocale picked for that request rather than
 * in whatever the receiving page happens to be in. The locale switch is the
 * sharpest case — it changes the language *and* announces itself, so its own
 * toast has to come back in the new language, which is the claim
 * Http\Controllers\Web\LocaleControllerTest documents but does not assert.
 */
test('renders the toast in the language the request settled on', function () {
    $this->from(route('home'))
        ->post(route('locale.update'), ['locale' => 'ar'])
        ->assertRedirect(route('home'))
        ->assertInertiaFlash('toast', ['type' => 'success', 'message' => 'تم تغيير اللغة.']);
});
