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
| UserPolicy, whose `view` takes a nullable user and returns true.
|
| A deactivated account's page is a **404**, not a 403, and it is decided
| before the controller: User::resolveRouteBinding() refuses to bind an
| inactive account. That moved out of the policy in the Phase 6 audit because
| the policy was never asked by the reviews vertical, which resolves a user by
| bare id through App\Enums\Reviewable::findVisibleOrFail() — so a deactivated
| account's reviews stayed readable and writable while their profile 403'd.
| UserPolicy::view still refuses them; it is no longer what a URL hits first.
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
| person it is about. A deactivated account is unreachable here for the same
| reason its page is: User::resolveRouteBinding() will not bind one, so this
| route 404s before the policy is asked. UserPolicy::like keeps its
| `isActive()` clause as belt and braces.
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
| Throttled by `locale-switches`, and it is the only route in this file whose
| limiter is keyed on the IP rather than on the caller: being public is the
| whole point of it, so there is usually no account to count against. It is
| also the only unauthenticated write in the application that is not an auth
| flow, and it is not as free as it looks — `SESSION_DRIVER=database` means a
| caller arriving without a cookie writes a `sessions` row, and this endpoint
| hangs off the header of every public page. 60 a minute is loose on purpose:
| it is far above a human changing language and far below what it takes to
| fill a table.
|
*/

Route::post('locale', [LocaleController::class, 'update'])
    ->middleware('throttle:locale-switches')
    ->name('locale.update');

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
| The reviewable itself is resolved through
| App\Enums\Reviewable::findVisibleOrFail(), which asks the target model's own
| resolveRouteBinding(). That is what makes a deactivated account's reviews a
| 404 on both halves of this vertical rather than a public list nobody can
| moderate — a gap the Phase 6 audit measured open (read 200, write 302) and
| User::resolveRouteBinding() closed.
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
| `pets.store` is throttled by `pet-listings`, and it is the one limiter in
| this file whose purpose is neither an inbox nor a moderation queue but the
| server itself. Publishing is the heaviest write in the application — up to
| four images, each stored and put through two conversions — and it carried no
| ceiling of any kind while `comments` capped a text row at 10 a minute:
| measured, one account published 25 listings with real uploads in a single
| burst, all 302. Two limits, because the two costs decay differently — 5 a
| minute for the CPU spike, 30 an hour for the disk.
|
| `pets.update` is throttled by `pet-listing-edits`, which is the same family
| one step looser: 10 a minute and 60 an hour, because a real owner corrects a
| listing far more often than they publish one while a single request costs
| exactly what `pets.store` costs — the same four images, the same two
| conversions each, run synchronously, because no queue worker is deployed.
|
| This comment used to argue the opposite: that the route needed no ceiling
| because ownership (PetPolicy) bounds it to listings that already exist, so a
| loop rewrites a fixed set of rows rather than growing one. That is wrong, and
| it is worth saying why rather than deleting it, because it is a plausible
| mistake to make twice. Ownership bounds how many *rows* a caller can touch;
| it bounds nothing about how much CPU and disk one owned row can be made to
| burn, and one pet re-uploaded in a loop is unbounded image conversion on a
| web worker. `profile.update` already carried a ceiling on precisely that
| argument for a *single* avatar (routes/settings.php); a route that accepts
| four images cannot be the uncapped one.
|
| `pets.destroy` and `pets.status.toggle` carry `content-edits`, the shared
| 30-a-minute ceiling described with the group in
| AppServiceProvider::configureRateLimiters(). Neither is expensive and
| neither notifies anybody — the ceiling is there so that the next route added
| beside them inherits one.
|
*/

Route::middleware(['auth', 'verified'])->group(function (): void {
    Route::get('pets/create', [PetController::class, 'create'])->name('pets.create');

    Route::post('pets', [PetController::class, 'store'])
        ->middleware('throttle:pet-listings')
        ->name('pets.store');

    Route::inertia('dashboard', 'Dashboard')->name('dashboard');

    Route::whereNumber('pet')->group(function (): void {
        Route::get('pets/{pet}/edit', [PetController::class, 'edit'])->name('pets.edit');

        Route::put('pets/{pet}', [PetController::class, 'update'])
            ->middleware('throttle:pet-listing-edits')
            ->name('pets.update');

        Route::delete('pets/{pet}', [PetController::class, 'destroy'])
            ->middleware('throttle:content-edits')
            ->name('pets.destroy');

        Route::patch('pets/{pet}/status', [PetController::class, 'toggleStatus'])
            ->middleware('throttle:content-edits')
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
| `comments.update` and `comments.destroy` carry `content-edits`, the shared
| 30-a-minute ceiling the update/destroy/toggle family across this file uses.
| Neither notifies anybody and both are bounded by CommentPolicy to the
| caller's own comment, so the ceiling is cheap insurance rather than a
| measured fix — but a route with no limiter is a precedent, and the next
| write added to this group would inherit it.
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

        Route::middleware('throttle:content-edits')
            ->whereNumber('comment')
            ->group(function (): void {
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
| `reviews.update` and `reviews.destroy` carry `content-edits`, the same
| shared 30-a-minute ceiling `comments.update` and `comments.destroy` do, and
| for the same reason: editing your own review notifies nobody, but leaving
| the pair uncapped makes "these do not need one" the rule that the next
| route in the group inherits.
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

        Route::middleware('throttle:content-edits')
            ->whereNumber('review')
            ->group(function (): void {
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
| `conversations.previews` is JSON for the same reason one level up: it
| feeds the header's messages menu, which is a panel on whatever page the
| user is already on, so it fetches five rows and the unread badge in one
| request rather than making them leave. The legacy app built that list into
| a shared Inertia prop on **every** page render — the arrangement
| `notifications.index` was deliberately not given, so that a page nobody
| opens the menu on costs no messaging query at all.
|
| It is the one list endpoint here that is **not paginated**, and `?page=` is
| meaningless on it: a dropdown does not page, and the `links` a paginator
| published pointed at a page nobody would fetch. It answers
| `{data, meta.unread_count}` out of ConversationPreviewResource, which is a
| narrower payload than `conversations.index` emits for the same rows —
| bytes, not permissions, are the axis, and the reasoning is in the resource.
|
| It is declared before the `{conversation}` group even though that group is
| whereNumber-constrained: the ordering is what keeps `conversations/previews`
| off `conversations.show` independently of that constraint surviving a
| future edit, the same belt-and-braces `notifications/read-all` has.
|
| It carries no throttle, and that is the decision rather than an omission.
| It is a GET that writes nothing, so the routing rule it would fall under
| ("every mutating route carries a named limiter") does not reach it, and no
| read route in this application is limited — `conversations.index`,
| `conversations.show` and `messages.index` are all open. Fetch frequency is
| not the axis: this is fired once per document load, which is the same rate
| the page around it is already being rendered and queried at, and its cost
| is five rows plus one aggregate — five queries, measured. A 429 here is a
| stale unread badge on a page that otherwise loaded fine, which is a worse
| outcome than the request. Throttling reads at all is an application-wide
| policy question, not something to settle one route at a time.
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
| The rest of the group is capped too, in two different families.
| `messages.update`, `messages.destroy` and `messages.pin` take
| `content-edits` — the shared 30-a-minute ceiling for editing rows you
| already own, which is what all three are. `conversations.read` takes
| `inbox-actions` at 60 a minute instead, because it is not a user gesture at
| all: the thread page fires it once it has rendered, so the ceiling has to
| clear a person opening threads quickly, and a 429 on it is an unread badge
| that will not clear. Sharing `content-edits` with it would let a session
| spent reading eat the allowance for editing a message.
|
*/

Route::middleware(['auth', 'verified'])->group(function (): void {
    Route::prefix('conversations')->name('conversations.')->group(function (): void {
        Route::get('/', [ConversationController::class, 'index'])->name('index');

        Route::get('previews', [ConversationController::class, 'previews'])->name('previews');

        Route::post('/', [ConversationController::class, 'store'])
            ->middleware('throttle:conversations')
            ->name('store');

        Route::whereNumber('conversation')->group(function (): void {
            Route::get('{conversation}', [ConversationController::class, 'show'])->name('show');

            Route::post('{conversation}/read', [ConversationController::class, 'markAsRead'])
                ->middleware('throttle:inbox-actions')
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
        ->middleware('throttle:content-edits')
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
| All three writes are throttled. `read` stays on `content-edits` at 30 a
| minute; `read-all` and `destroy-all` take `inbox-actions` at 60, the same
| ceiling `conversations.read` has.
|
| `read` is the odd member of the `content-edits` family — the only route in
| it fired once *per row in a list* rather than once per item page — and
| clearing a 20-row bell one row at a time spends two thirds of a bucket it
| shares with comment, review, message and pet edits. What settles it there
| anyway is that **`read-all` is the pressure valve**: a user facing a full
| bell has a one-request way through, so per-row clicking is a preference
| rather than the only path, and 30 a minute is a ceiling nobody is forced
| into. Revisit trigger, and it is the only one: **if `read-all` ever leaves
| the UI, move `read` to `inbox-actions`** with its siblings, because the
| valve is then gone and per-row marking becomes the only way to clear a bell.
|
| Do not re-argue this on a "who fires it / a person clicks that row" axis.
| That was the original justification and it was rejected: it does not
| distinguish `read` from `conversations.read`, which a person also clicks and
| which sits on `inbox-actions`.
|
| `destroy-all` is the one that needs a number rather than a principle: it
| deletes every notification the viewer has, so an accidental client-side loop
| is destructive in a way a slow one is not. It is generous rather than tight
| because the loop is also idempotent — the first pass empties the list and
| every pass after it deletes nothing — so the ceiling is there to end the
| loop, not to ration somebody clearing their bell twice.
|
| One more thing not to write here: `content-edits` is bounded to rows the
| caller owns by a policy for nine of its ten routes, but not for this one.
| `read` is bounded by the owning relation instead — someone else's id is a
| 404 out of `firstOrFail()`, not a 403.
|
*/

Route::middleware(['auth', 'verified'])
    ->prefix('notifications')
    ->name('notifications.')
    ->group(function (): void {
        Route::get('/', [NotificationController::class, 'index'])->name('index');

        Route::post('read-all', [NotificationController::class, 'markAllAsRead'])
            ->middleware('throttle:inbox-actions')
            ->name('read-all');

        Route::post('{notification}/read', [NotificationController::class, 'markAsRead'])
            ->whereUuid('notification')
            ->middleware('throttle:content-edits')
            ->name('read');

        Route::delete('/', [NotificationController::class, 'destroyAll'])
            ->middleware('throttle:inbox-actions')
            ->name('destroy-all');
    });

require __DIR__.'/settings.php';
