<?php

use App\Http\Controllers\Web\CommentController;
use App\Http\Controllers\Web\ConversationController;
use App\Http\Controllers\Web\HomeController;
use App\Http\Controllers\Web\LocaleController;
use App\Http\Controllers\Web\MessageController;
use App\Http\Controllers\Web\NotificationController;
use App\Http\Controllers\Web\PetController;
use App\Http\Controllers\Web\ProfileController;
use App\Http\Controllers\Web\ReportController;
use App\Http\Controllers\Web\ReviewController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Public
|--------------------------------------------------------------------------
|
| The discovery feed and a single listing are reachable without an account:
| a shared link has to work for somebody who has never signed in. Both
| payloads hide the owner-only fields.
|
| Every route is named, because the frontend calls them through Wayfinder.
| No controller method is registered at two URIs: Wayfinder emits a
| URI-keyed object instead of a callable when it sees a duplicate, which
| breaks the generated import at runtime.
|
*/

Route::get('/', [HomeController::class, 'index'])->name('home');

Route::get('pets/{pet}', [PetController::class, 'show'])
    ->whereNumber('pet')
    ->name('pets.show');

/*
|--------------------------------------------------------------------------
| Help and support
|--------------------------------------------------------------------------
|
| The last two legacy pages, and the only two in the whole application with
| no controller behind them. Both were `Route::get` closures returning
| `Inertia::render('Help/Index')` / `Inertia::render('Support/Index')` with
| no props, and both templates are static: every string comes from the
| translation files (the `help.*` and `support.*` keys are already in
| lang/en.json and lang/ar.json), the FAQ rows and the "Start chat" button
| carry no handler, and the only address on the page is a `mailto:` in the
| markup. There is nothing for a controller to do, so there is no
| controller — `Route::inertia()` is the whole of it, the same as
| `settings/appearance` and `dashboard`.
|
| Public, and deliberately so: "how does this work" and "how do I reach a
| human" are the two questions somebody without an account is most likely to
| have. The legacy routes were public too.
|
| Components are `Help.vue` / `Support.vue` at the top level of
| resources/js/pages, beside `Dashboard.vue` and `Home.vue`, rather than the
| legacy `Help/Index` — this app does not use a directory per single page.
|
| No props. If a real support address or an office-hours line is ever wanted
| it does **not** go in a third argument here: `Route::inertia()` stores its
| props as route defaults, which `route:cache` serialises, so a `config()`
| call at registration time would freeze into the cached route file. That
| would need a controller, or shared props.
|
*/

Route::inertia('help', 'Help')->name('help');
Route::inertia('support', 'Support')->name('support');

/*
|--------------------------------------------------------------------------
| Public profiles
|--------------------------------------------------------------------------
|
| A profile is a public page: a shared listing links to its owner and a
| review is a public statement about a named person, so a signed-out
| visitor has to be able to read one. This route therefore sits **outside**
| the auth group rather than inside it with `auth` peeled off.
|
| That distinction is the fix for the legacy declaration, which was
| `->name('profile.show')->withoutMiddleware('auth')` inside a
| `['auth', 'verified']` group. Removing `auth` while leaving `verified`
| means EnsureEmailIsVerified still runs, finds no user, and redirects to
| `verification.notice` — so the one route explicitly marked public was
| unreachable to the public, and every shared profile link bounced a
| signed-out visitor to a verification screen.
|
| Guest visibility is a recorded decision, not the absence of a check:
| ProfileController::show calls $this->authorize('view', $user) against
| UserPolicy, whose `view` takes a nullable user and returns true — and
| which is also where a deactivated account's page is hidden.
|
| `{user}` is constrained to digits and binds by **id**. It is deliberately
| not keyed on `username`: App\Enums\Reviewable and Reportable resolve
| their morph targets through resolveRouteBinding(), so a User keyed on a
| string column would have every one of those lookups compare an integer id
| against it.
|
| Editing lives at `settings/profile` (routes/settings.php) and nowhere
| else. There is no `profile/{user}/edit`.
|
| ModelReviewedNotification and ModelLikedNotification both build their deep
| link with `Route::has('profile.show')`, so this route is what turns those
| payloads' `url` from null into a link.
|
*/

Route::get('profile/{user}', [ProfileController::class, 'show'])
    ->whereNumber('user')
    ->name('profile.show');

/*
|--------------------------------------------------------------------------
| Profile likes
|--------------------------------------------------------------------------
|
| The write half of the public profile page, and the one that was missing.
| App\Models\User has implemented App\Contracts\Likeable since the like
| vertical landed — it carries HasLikes, its own likeNotificationRecipients()
| and a LikeFactory::forUser() state — but no route ever reached it, so
| Http\Resources\Profile\ProfileResource emitted an `is_liked` flag that
| nothing in the application could flip. Liking a person was a first-class
| feature of the legacy app; this is it, on the current arrangement.
|
| Verified account only (UserPolicy::like), because the like notifies the
| person it is about. That policy also re-derives what UserPolicy::view
| already decided about a deactivated account: a profile whose page is a 403
| must not stay likeable at a guessable sequential id.
|
| Throttled by `profile-likes`, sized identically to `pet-likes` and
| `comment-likes` — it is the same gesture on a third model — and kept as its
| own limiter rather than shared with them so a spree in one place cannot eat
| a visitor's allowance in another.
|
| One toggle route, not a like and an unlike, matching `pets.like`,
| `comments.like` and `pets.status.toggle`.
|
*/

Route::middleware(['auth', 'verified'])->group(function (): void {
    Route::post('profile/{user}/like', [ProfileController::class, 'toggleLike'])
        ->whereNumber('user')
        ->middleware('throttle:profile-likes')
        ->name('profile.like');
});

/*
|--------------------------------------------------------------------------
| Locale
|--------------------------------------------------------------------------
|
| Public, because the language picker is in the header of every page
| including the ones a guest can read, and a guest's choice has to stick —
| they have no row to write it to, so the cookie the Action queues is the
| only thing that survives their next visit.
|
| A POST rather than a GET, even though it reads like navigation. It writes
| a cookie, a session key and (when signed in) a column, and Inertia v3
| issues real GET requests on hover for prefetching and instant visits, so
| a `GET /locale/ar` link would switch the whole site to Arabic as the
| pointer crossed the menu. Same reason `conversations.read` is a POST.
|
*/

Route::post('locale', [LocaleController::class, 'update'])->name('locale.update');

/*
|--------------------------------------------------------------------------
| Comment threads
|--------------------------------------------------------------------------
|
| Reading a thread is as public as the page it hangs off. Both of these
| return JSON rather than an Inertia page: a page already ships a bounded
| first slice of its thread, and these are how the rest of it is paged in
| without a visit. `comments.index` pages the top-level comments (each with
| a bounded reply preview) and `comments.replies` pages one comment's
| replies — replies cannot be paginated per parent inside a single eager
| load, so expanding one is a request of its own.
|
| `{commentable_type}` is bound to the App\Enums\Commentable enum, so an
| unknown target type is a 404 at the router and no controller ever sees a
| model class name that came off the wire.
|
| `comments/{comment}/replies` is declared before
| `comments/{commentable_type}/{commentable_id}` even though whereNumber()
| already keeps them apart: both are three segments, and the ordering is
| what makes that independent of the constraint surviving a future edit.
|
| `comments.replies` addresses a comment by id and never names the listing,
| so the listing's visibility cannot come from the URL. It comes from
| Comment::resolveRouteBinding(), which will not bind a comment whose
| commentable is hidden — otherwise a retired listing's discussion, and
| every author's name, username and location with it, stayed readable at a
| guessable sequential id while the page it belongs to 404'd.
|
*/

Route::prefix('comments')->name('comments.')->group(function (): void {
    Route::get('{comment}/replies', [CommentController::class, 'replies'])
        ->whereNumber('comment')
        ->name('replies');

    Route::get('{commentable_type}/{commentable_id}', [CommentController::class, 'index'])
        ->whereNumber('commentable_id')
        ->name('index');
});

/*
|--------------------------------------------------------------------------
| Reviews
|--------------------------------------------------------------------------
|
| A profile's reviews are part of the public page they belong to, so reading
| them needs no account. This returns JSON rather than an Inertia page, the
| same split `comments.index` has: the page ships a bounded first slice and
| this is how the rest is paged in without a visit.
|
| `{reviewable_type}` is bound to the App\Enums\Reviewable enum, so an
| unknown target type is a 404 at the router and no controller ever sees a
| model class name that came off the wire.
|
| That binding is the fix for the worst hole in the legacy application. The
| legacy route was `POST reviews/store/{type}/{reviewable_id}` and the
| controller's first statement was `$type::find($request->reviewable_id)` —
| a raw URL segment invoked as a static call on a user-supplied class name,
| with no whitelist, no enum and no validation of the segment. Closing it at
| the router rather than in the controller means every future review route
| inherits the fix by construction: the parameter arrives typed, and there
| is no code path that could receive a string instead.
|
*/

Route::get('reviews/{reviewable_type}/{reviewable_id}', [ReviewController::class, 'index'])
    ->whereNumber('reviewable_id')
    ->name('reviews.index');

/*
|--------------------------------------------------------------------------
| Listing management
|--------------------------------------------------------------------------
|
| Publishing, editing and liking need a verified account; ownership on top of
| that is decided by PetPolicy, which every action calls with
| $this->authorize(). The pet parameter is constrained to digits so
| `pets/create` can never be swallowed by `pets/{pet}`.
|
| The like route is throttled: it is a POST that writes a like, which fires
| LikeObserver and sends the owner a database notification, so an unthrottled
| tap loop is a notification flood.
|
*/

Route::middleware(['auth', 'verified'])->group(function (): void {
    Route::get('pets/create', [PetController::class, 'create'])->name('pets.create');
    Route::post('pets', [PetController::class, 'store'])->name('pets.store');

    Route::inertia('dashboard', 'Dashboard')->name('dashboard');

    Route::whereNumber('pet')->group(function (): void {
        Route::get('pets/{pet}/edit', [PetController::class, 'edit'])->name('pets.edit');
        Route::put('pets/{pet}', [PetController::class, 'update'])->name('pets.update');
        Route::delete('pets/{pet}', [PetController::class, 'destroy'])->name('pets.destroy');

        Route::patch('pets/{pet}/status', [PetController::class, 'toggleStatus'])
            ->name('pets.status.toggle');

        Route::post('pets/{pet}/like', [PetController::class, 'toggleLike'])
            ->middleware('throttle:pet-likes')
            ->name('pets.like');
    });
});

/*
|--------------------------------------------------------------------------
| Comment writes
|--------------------------------------------------------------------------
|
| Writing, editing, deleting and liking a comment all need a verified
| account; who may touch which comment is CommentPolicy's decision, called
| with $this->authorize() in every action. Whether the *target* accepts a
| comment is not decided here at all. `comments.store` names the target in
| the URL and the publish pipeline resolves it through
| App\Enums\Commentable::findOrFail(); `comments.like`, `comments.update`
| and `comments.destroy` name only a comment, and Comment's route binding
| resolves the target for them. Either way a soft-deleted listing is a 404
| rather than a comment nobody can read.
|
| `comments.store` and `comments.like` are throttled by named limiters
| (AppServiceProvider::configureRateLimiters). Both writes send the owner or
| the author a database notification, so an unthrottled loop is a
| notification flood; the legacy routes were throttled by nothing.
|
| `comments.destroy`, not the legacy `comments.delete` — the verb now
| matches the method, as it does everywhere else in this file.
|
*/

Route::middleware(['auth', 'verified'])
    ->prefix('comments')
    ->name('comments.')
    ->group(function (): void {
        Route::post('{comment}/like', [CommentController::class, 'toggleLike'])
            ->whereNumber('comment')
            ->middleware('throttle:comment-likes')
            ->name('like');

        Route::post('{commentable_type}/{commentable_id}', [CommentController::class, 'store'])
            ->whereNumber('commentable_id')
            ->middleware('throttle:comments')
            ->name('store');

        Route::whereNumber('comment')->group(function (): void {
            Route::put('{comment}', [CommentController::class, 'update'])->name('update');
            Route::delete('{comment}', [CommentController::class, 'destroy'])->name('destroy');
        });
    });

/*
|--------------------------------------------------------------------------
| Review writes
|--------------------------------------------------------------------------
|
| Writing, editing and deleting a review all need a verified account; who
| may touch which review is ReviewPolicy's decision, called with
| $this->authorize() in every action. The legacy store route called no
| policy at all, so any authenticated session could review anyone.
|
| `reviews.store` names the target in the URL, bound to App\Enums\Reviewable
| exactly as the read route is; the submit pipeline resolves it from the
| enum and refuses a self-review, a duplicate, or a target that is gone.
| `reviews.update` and `reviews.destroy` name only a review, and
| Review::resolveRouteBinding() refuses to bind one whose reviewable has
| vanished — reviews reach their target through a morph column, which
| carries no foreign key, so deleting the reviewed user strands the reviews
| about them and nothing in the database says so.
|
| Paths are `reviews/{review}`, not the legacy `reviews/update/{review}` and
| `reviews/destroy/{review}`: the HTTP verb already says what is happening,
| and the legacy shape put every method at a URI that repeated it.
|
| `reviews.store` is throttled by a named limiter
| (AppServiceProvider::configureRateLimiters). A review is public content
| that notifies the person it is about, so an unthrottled loop is a
| notification flood. The legacy review routes had no throttle of any kind.
|
*/

Route::middleware(['auth', 'verified'])
    ->prefix('reviews')
    ->name('reviews.')
    ->group(function (): void {
        Route::post('{reviewable_type}/{reviewable_id}', [ReviewController::class, 'store'])
            ->whereNumber('reviewable_id')
            ->middleware('throttle:reviews')
            ->name('store');

        Route::whereNumber('review')->group(function (): void {
            Route::put('{review}', [ReviewController::class, 'update'])->name('update');
            Route::delete('{review}', [ReviewController::class, 'destroy'])->name('destroy');
        });
    });

/*
|--------------------------------------------------------------------------
| Reports
|--------------------------------------------------------------------------
|
| One route: filing a report. Nothing reads a report back on this guard —
| triage happens in Nova on the `admins` guard and is Phase 3's.
|
| `{reportable_type}` is bound to the App\Enums\Reportable enum. The legacy
| route was a bare `POST reports` whose StoreReportRequest accepted
| `reportable_type` as `['required', 'string']` — an unrestricted class name
| in the request body — and then ran its self-report and duplicate guards
| only when that string was `Review::class` or `Comment::class`. Every other
| value skipped both guards and was written to the morph column as sent.
| Moving the target into the URL under an enum binding is what makes the
| whitelist unavoidable: there is no longer a request key to widen, and a
| type the guards cannot run against raises ReportingNotSupported instead of
| being filed.
|
| Throttled by a named limiter, and this is the one endpoint where that is
| load bearing rather than tidy: an unthrottled report route buries the
| moderation queue, and every accepted report now writes a notification row
| per moderator.
|
*/

Route::middleware(['auth', 'verified'])->group(function (): void {
    Route::post('reports/{reportable_type}/{reportable_id}', [ReportController::class, 'store'])
        ->whereNumber('reportable_id')
        ->middleware('throttle:reports')
        ->name('reports.store');
});

/*
|--------------------------------------------------------------------------
| Messaging
|--------------------------------------------------------------------------
|
| Nothing here is public: a conversation has no guest-readable page, so the
| whole group sits behind `auth` and `verified` and every action calls
| $this->authorize() against ConversationPolicy or MessagePolicy.
|
| `conversations.show` is a pure read. The legacy controller marked the
| thread read inside that GET, which made rendering a page mutate state —
| and under Inertia v3, whose prefetching and instant visits issue real GET
| requests on hover, it would have cleared the unread badge for threads
| nobody opened. The cursor now moves on `conversations.read`, a POST the
| thread page fires once it has rendered.
|
| `conversations/{conversation}/messages` is the thread's paging endpoint
| and returns JSON, the same split `comments.index` has: the page ships the
| newest page of messages and this is how the rest arrives without a visit.
|
| `messages.update`, `messages.destroy` and `messages.pin` address a message
| by id and never name its conversation, so the conversation's visibility
| cannot come from the URL. It comes from Message::resolveRouteBinding(),
| which will not bind a message whose conversation is soft-deleted —
| otherwise a retired thread stayed writable at a guessable sequential id
| while the page it belongs to 404'd. Same fix, same reason, as
| Comment::resolveRouteBinding().
|
| Two named limiters (AppServiceProvider::configureRateLimiters) guard the
| writes that reach another person. `conversations` is the tightest in the
| app — 5 a minute and 30 a day — because opening a thread is the only write
| that puts something in the inbox of somebody who never asked for it, which
| the legacy app allowed without any limit and with a
| ConversationPolicy::create that returned `true`. `messages` is looser,
| because a conversation's other side already agreed to be there.
|
*/

Route::middleware(['auth', 'verified'])->group(function (): void {
    Route::prefix('conversations')->name('conversations.')->group(function (): void {
        Route::get('/', [ConversationController::class, 'index'])->name('index');

        Route::post('/', [ConversationController::class, 'store'])
            ->middleware('throttle:conversations')
            ->name('store');

        Route::whereNumber('conversation')->group(function (): void {
            Route::get('{conversation}', [ConversationController::class, 'show'])->name('show');

            Route::post('{conversation}/read', [ConversationController::class, 'markAsRead'])
                ->name('read');

            Route::get('{conversation}/messages', [MessageController::class, 'index'])
                ->name('messages.index');

            Route::post('{conversation}/messages', [MessageController::class, 'store'])
                ->middleware('throttle:messages')
                ->name('messages.store');
        });
    });

    Route::prefix('messages')
        ->name('messages.')
        ->whereNumber('message')
        ->group(function (): void {
            Route::put('{message}', [MessageController::class, 'update'])->name('update');
            Route::delete('{message}', [MessageController::class, 'destroy'])->name('destroy');
            Route::post('{message}/pin', [MessageController::class, 'togglePin'])->name('pin');
        });
});

/*
|--------------------------------------------------------------------------
| Notifications
|--------------------------------------------------------------------------
|
| The inbox behind the bell. Nothing here is public and nothing here names
| a second party: every action reads or writes `$request->user()`'s own
| notifications through their own relation, so ownership is enforced by the
| query and there is no policy — someone else's id is a 404 from
| firstOrFail(), not a 403 from a check that could be forgotten.
|
| `index` returns JSON, the same split `comments.index` and `reviews.index`
| have: the bell is a panel on whatever page the user is already on, so it
| fetches its list rather than making them leave, and the unread badge
| rides along in the paginator's `meta`. The legacy app put 20 rows plus
| the count into the shared Inertia props of **every** page render instead.
|
| `read` is a POST, not a GET that marks-on-render. Inertia v3 prefetches
| on hover, so a read-on-GET endpoint would clear the badge as the pointer
| crossed the menu — the same reasoning that split `conversations.read` out
| of `conversations.show`.
|
| `notifications/read-all` is declared before `notifications/{notification}/read`
| even though they are different lengths and the id is UUID-constrained:
| the ordering is what keeps them apart independently of that constraint
| surviving a future edit.
|
| `{notification}` is a UUID — `notifications` is the framework's own table
| with a string primary key — so it is constrained with whereUuid() rather
| than whereNumber(), and it arrives as a plain string. It is not route
| model bound: binding the framework's DatabaseNotification would still not
| scope it to the viewer, which is the only question that matters.
|
*/

Route::middleware(['auth', 'verified'])
    ->prefix('notifications')
    ->name('notifications.')
    ->group(function (): void {
        Route::get('/', [NotificationController::class, 'index'])->name('index');

        Route::post('read-all', [NotificationController::class, 'markAllAsRead'])->name('read-all');

        Route::post('{notification}/read', [NotificationController::class, 'markAsRead'])
            ->whereUuid('notification')
            ->name('read');

        Route::delete('/', [NotificationController::class, 'destroyAll'])->name('destroy-all');
    });

require __DIR__.'/settings.php';
