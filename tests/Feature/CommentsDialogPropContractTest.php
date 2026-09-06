<?php

use App\Enums\ReportCategory;
use App\Enums\ReportReason;
use App\Models\Comment;
use App\Models\Pet;
use App\Models\User;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia;

/**
 * The page props `components/comments/CommentsDialog.vue` reads, pinned across
 * every page that mounts it.
 *
 * One component consumes three pages. `Home` and `profile.show` mount it from a
 * feed card through `PetCardCommentButton`; `pets.show` mounts the same list
 * and composer inline through `CommentThread`. Every prop it reads is
 * **optional** the whole way down — `maxLength`, `reportCategories` and
 * `reportReasons` are all `?` on the Vue side — so a page that stops sending
 * one turns off exactly one control and nothing else: no character counter
 * under the composer, or no report entry in a comment's menu. Nothing throws,
 * `vue-tsc` stays clean, and every per-page test stays green. That is the
 * regression class this file exists for, and it is why the assertions are
 * per-page rather than "somebody sends it".
 *
 * `Home` shipping the two report lists is the repair of a parity regression —
 * the legacy controller sent `ReportReason::options()` and this one had sent
 * neither — and it is two lines in a render array. Pinning them per page is
 * what stops the next reader deleting them as unused controller props.
 *
 * ## Why one known value rather than three payloads compared to each other
 *
 * "The three pages agree" and "each page equals `ReportCategory::options()`"
 * are the same assertion, and the second is the stronger of the two: three
 * payloads compared only to each other still agree when all three are wrong,
 * and the failure names no page. So agreement is asserted by pinning each page
 * against one known value, and the dataset case name says which page broke.
 *
 * The `commentBounds` half is asserted against a re-`config()`d value rather
 * than today's default, because a prop frozen at the default agrees with the
 * drift it exists to catch — the same reasoning
 * `PetControllerTest`'s "ships the comment bounds" test records.
 *
 * ## Overlaps, kept deliberately
 *
 * `ProfileControllerTest`'s "ships the report vocabulary the review report
 * dialog needs" asserts the same two props on one of these three pages. It is
 * about the *review* report dialog and reads as part of that page's own
 * contract; this file is about the comment dialog's cross-page one. The
 * `profile/Show` case below therefore duplicates it, and is kept so that the
 * three pages are asserted as a set rather than two of three.
 *
 * ## What is covered elsewhere, so that it is not covered twice here
 *
 * The composer gate (`CommentComposerGate.vue`) refuses an *unverified* reader,
 * not merely a signed-out one, and the predicate it mirrors is the route's:
 * `comments.store` is `auth` **+ `verified`**. That predicate is already pinned
 * behaviourally, in both directions, by `CommentControllerTest` — "redirects a
 * guest to the login page and writes nothing" and "redirects an unverified user
 * to the verification notice and writes nothing", with the same pair on
 * `update`, `destroy` and `like`. Dropping `verified` from the route group
 * fails those tests by name, which is exactly what a gate that widens who sees
 * a composer needs. Nothing is added here for it; a second copy would detect no
 * defect the existing pair misses.
 *
 * ## The trigger's count arithmetic: a deliberate choice not to add a harness
 *
 * `PetCardCommentButton.vue` holds a local `offset` that the dialog's `posted`
 * and `deleted` emits move — `+1` per post, `-(1 + replies_count)` per root
 * delete, clamped at `-commentsCount` so a stale feed snapshot cannot render a
 * negative count. None of that arithmetic is covered by any test in this
 * repository, and this is a **deliberate choice not to add a harness** for it
 * rather than a claim that it cannot be checked.
 *
 * The distinction is load-bearing, so state it plainly: this behaviour **is**
 * verifiable. It was verified in a real browser this phase — an isolated copy
 * of the tree, `npm run build`, `php artisan serve` and Chrome driven over
 * CDP — including the stale-snapshot case, where deleting a root whose
 * `replies_count` outran the feed's `comments_count` rendered `0` rather than a
 * negative. What is missing is not the ability to check it, it is a check that
 * runs in CI: `package.json` installs `vite`, `vue-tsc` and `vp check` and no
 * test runner, so a component test would mean adding a dependency, which is a
 * decision for whoever owns `package.json` and not one to make inside a test
 * file. Do not read this paragraph as "not testable"; an unattempted check and
 * an impossible one must not read alike.
 *
 * ## The dialog's failure path is client-side, and only its server half is here
 *
 * `CommentList.vue` shipped broken once — `reload()` cleared the seeded rows
 * *before* asking the endpoint, so a rejected request left "No comments yet" on
 * a listing with forty comments. The fix keeps the rendered rows, records the
 * failed request and offers a retry that replays exactly it. Three of those
 * four facts are rendering decisions with no server surface at all: that the
 * error state renders instead of the empty state, that the rows already on
 * screen survive, and that the retry replays the *same* `{page, replace}` pair
 * rather than page one. They are checkable in a browser by the method above and
 * are not checkable through an HTTP test, and they are uncovered for the same
 * reason as the offsets.
 *
 * The half that is server-side is the envelope the retry and the pager are
 * driven from, and it is pinned below.
 */
const COMMENTS_DIALOG_HOST_COMPONENTS = ['Home', 'pets/Show', 'profile/Show'];

/**
 * A URL that renders one of the three pages that mount the comments dialog,
 * with whatever record that page is addressed by.
 */
function commentsDialogHostUrl(string $component): string
{
    return match ($component) {
        'Home' => route('home'),
        'pets/Show' => route('pets.show', Pet::factory()->create()),
        'profile/Show' => route('profile.show', User::factory()->create()),
    };
}

/**
 * The URL of a listing's comment thread. `pet` is the morph alias the enum is
 * backed by and the value the column stores; a class name never travels here.
 *
 * @return array{commentable_type: string, commentable_id: int}
 */
function commentsDialogThreadParameters(Pet $pet): array
{
    return ['commentable_type' => 'pet', 'commentable_id' => $pet->getKey()];
}

test('ships the report vocabulary and the comment ceiling the dialog reads', function (string $component) {
    config([
        'petconnect.comments.max_length' => 140,
        'petconnect.comments.thread_per_page' => 3,
    ]);

    $this->get(commentsDialogHostUrl($component))
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->component($component)
            ->where('reportCategories', ReportCategory::options())
            ->where('reportReasons', ReportReason::options())
            ->where('commentBounds', ['max_length' => 140, 'thread_per_page' => 3]));
})->with(COMMENTS_DIALOG_HOST_COMPONENTS);

/**
 * The counter under the composer and the rule that refuses the comment have to
 * be the same number, and the failure a reader experiences when they are not is
 * a box that let them type 1,200 characters and a 422 that stranded the text.
 *
 * Asserted as a *value*, not a shape: `commentBounds()` and the `max:` rule are
 * built from the same `CommentValidationRules::maxContentLength()` accessor
 * today, and this is what fails if either end ever reads `config()` for itself
 * again. The ceiling driven through the endpoint is the one read off the
 * rendered page rather than off `config()`, so a prop hardcoded at a number the
 * validator does not share fails here — which is the whole difference from
 * `CommentControllerTest`'s "rejects a comment longer than the configured
 * ceiling", which reads both ends from the config and would agree with a
 * hardcoded prop.
 *
 * `Home` is the page it is read from because it is the one that never used to
 * send it at all.
 */
test('ships a composer ceiling that is the one comments.store enforces', function () {
    config(['petconnect.comments.max_length' => 140]);
    $author = User::factory()->create();
    $pet = Pet::factory()->create();

    $maxLength = $this->get(route('home'))
        ->assertOk()
        ->viewData('page')['props']['commentBounds']['max_length'];

    $this->actingAs($author)
        ->from(route('home'))
        ->post(route('comments.store', commentsDialogThreadParameters($pet)), [
            'content' => Str::repeat('a', $maxLength),
        ])
        ->assertValid();

    $this->actingAs($author)
        ->from(route('home'))
        ->post(route('comments.store', commentsDialogThreadParameters($pet)), [
            'content' => Str::repeat('b', $maxLength + 1),
        ])
        ->assertInvalid(['content' => 'must not be greater than '.$maxLength]);

    expect(Comment::query()->sole()->content)->toBe(Str::repeat('a', $maxLength));
});

/**
 * `CommentList.vue` reads three things out of every response and nothing else:
 * `data`, `meta.current_page` and `meta.last_page`. The last two are what
 * `hasMore` is answered from once a fetch has happened, and the dialog fetches
 * as it opens — it ships no root count to compare against, so from its first
 * response onwards the paginator's own `last_page` is the *only* thing that
 * decides whether "load more" exists. Dropping either key from the envelope
 * leaves `undefined < undefined`, which is `false`: the button vanishes and the
 * rest of the thread is unreachable, silently.
 *
 * The `?page=` half is the other end of the retry. A failed request is replayed
 * with the exact page it asked for, so an endpoint that ignored the parameter
 * would answer a retry of page 3 with page 1 and the merge would deduplicate it
 * to nothing.
 *
 * Distinct from "pages the thread at the configured size", which asserts
 * `meta.per_page` and `meta.total` on page one alone.
 */
test('answers a page of the thread with the envelope the dialog pages and retries by', function () {
    config(['petconnect.comments.thread_per_page' => 2]);
    $pet = Pet::factory()->create();
    Comment::factory()->count(5)->for($pet, 'commentable')->create();

    $this->getJson(route('comments.index', [
        ...commentsDialogThreadParameters($pet),
        'page' => 2,
    ]))
        ->assertOk()
        ->assertJsonCount(2, 'data')
        ->assertJsonPath('meta.current_page', 2)
        ->assertJsonPath('meta.last_page', 3);
});
