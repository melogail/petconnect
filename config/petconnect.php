<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Nearby Pets Search Radius
    |--------------------------------------------------------------------------
    |
    | Default radius used when the home feed resolves the visitor location.
    | Max is the upper validation bound for the radius query parameter.
    | Both values are in kilometers.
    |
    */

    'nearby' => [
        'default_radius_km' => (float) env('NEARBY_PETS_DEFAULT_RADIUS_KM', 20),
        'min_radius_km' => (float) env('NEARBY_PETS_MIN_RADIUS_KM', 1),
        'max_radius_km' => (float) env('NEARBY_PETS_MAX_RADIUS_KM', 100),
    ],

    /*
    |--------------------------------------------------------------------------
    | Home Feed Filters
    |--------------------------------------------------------------------------
    |
    | Bounds and defaults for the pet discovery filter sheet.
    |
    */

    'filters' => [
        'max_age_years' => (float) env('HOME_FILTERS_MAX_AGE_YEARS', 15),
        'default_age_min' => (float) env('HOME_FILTERS_DEFAULT_AGE_MIN', 0),
        'default_age_max' => (float) env('HOME_FILTERS_DEFAULT_AGE_MAX', 15),
    ],

    /*
    |--------------------------------------------------------------------------
    | Pet Listings
    |--------------------------------------------------------------------------
    |
    | Page size for the home feed and the upload limits the pet form enforces.
    | The gallery limit excludes the featured photo, which is validated on its
    | own key; sizes are in kilobytes, matching Laravel's `max` file rule. It is
    | a per-listing lifetime cap, not a per-request one: an edit is rejected when
    | the photos already attached, minus the ones it deletes, plus the ones it
    | uploads, would exceed it.
    |
    | The comment bounds keep a thread from dominating a payload. A feed card
    | carries a preview of the newest few top-level comments and no replies; the
    | detail page carries the newest page of top-level comments with a few of
    | the newest replies each. Both always ship the true `comments_count`.
    |
    | A view is counted at most once per visitor per dedup window, so the
    | counter cannot be inflated by reloading the page.
    |
    */

    'pets' => [
        'feed_per_page' => (int) env('PETS_FEED_PER_PAGE', 12),
        'max_gallery_images' => (int) env('PETS_MAX_GALLERY_IMAGES', 3),
        'max_image_kilobytes' => (int) env('PETS_MAX_IMAGE_KILOBYTES', 512),
        'feed_comment_preview' => (int) env('PETS_FEED_COMMENT_PREVIEW', 3),
        'detail_comment_page_size' => (int) env('PETS_DETAIL_COMMENT_PAGE_SIZE', 20),
        'detail_reply_preview' => (int) env('PETS_DETAIL_REPLY_PREVIEW', 3),
        'view_dedup_minutes' => (int) env('PETS_VIEW_DEDUP_MINUTES', 60),
    ],

    /*
    |--------------------------------------------------------------------------
    | Comment Threads
    |--------------------------------------------------------------------------
    |
    | `max_length` is the ceiling the comment Form Requests enforce. The column
    | is `text`, so this is the only limit there is: the legacy app validated
    | max:500 against a varchar(255), which meant a 300-character comment either
    | truncated or threw a driver error depending on strict mode. Change this
    | and nothing else has to move — nothing derives a column width from it.
    |
    | The thread endpoint pages top-level comments `thread_per_page` at a time,
    | each carrying its newest `reply_preview` replies plus the true
    | `replies_count`; a comment whose replies overflow that preview is expanded
    | through the replies endpoint, which pages `replies_per_page` at a time.
    | These are the paginated equivalents of `pets.detail_comment_page_size` and
    | `pets.detail_reply_preview`, which bound the copy of the thread that ships
    | inside the pet detail payload on first render.
    |
    */

    'comments' => [
        'max_length' => (int) env('COMMENTS_MAX_LENGTH', 1000),
        'thread_per_page' => (int) env('COMMENTS_THREAD_PER_PAGE', 20),
        'reply_preview' => (int) env('COMMENTS_REPLY_PREVIEW', 3),
        'replies_per_page' => (int) env('COMMENTS_REPLIES_PER_PAGE', 10),
    ],

    /*
    |--------------------------------------------------------------------------
    | Messaging
    |--------------------------------------------------------------------------
    |
    | `max_length` is the ceiling the message Form Requests enforce against a
    | `text` column, so it is the only limit there is. It is deliberately larger
    | than `comments.max_length`: a private message is correspondence, not a
    | public one-liner.
    |
    | `inbox_per_page` bounds the conversation list and `thread_per_page` the
    | messages inside one conversation. Both endpoints page newest first — the
    | first page of a thread is the end of the conversation, which is the part a
    | reader wants, and older pages are fetched backwards from there.
    |
    | `preview_per_page` is the much smaller slice behind the header's messages
    | menu (`conversations.previews`), which is a peek rather than a list: it
    | shows the newest handful and links to the inbox for the rest. It is its
    | own key rather than a reuse of `inbox_per_page` because the two answer
    | different questions — how many rows fit in a dropdown, and how many rows
    | are worth one request on a page devoted to them — and tuning either one to
    | suit the other would be tuning the wrong screen.
    |
    | `preview_snippet_length` is how much of the last message that menu is
    | given, truncated on the server by ConversationPreviewResource. It is a
    | display bound rather than a validation one, which is why it is not derived
    | from `max_length`: that endpoint is fetched once per document load by
    | every signed-in visitor, and shipping five 5,000-character messages to
    | draw five one-line rows is ~25 KB nobody reads.
    |
    | `edit_window_minutes` is how long after sending a message may still be
    | rewritten, enforced by MessagePolicy::update. The legacy app had no window
    | at all, so a message could be rewritten indefinitely — including long
    | after the other side had read and acted on it, with no trace. Set it to 0
    | to make messages immutable once sent.
    |
    */

    'messaging' => [
        'max_length' => (int) env('MESSAGES_MAX_LENGTH', 5000),
        'inbox_per_page' => (int) env('MESSAGES_INBOX_PER_PAGE', 15),
        'preview_per_page' => (int) env('MESSAGES_PREVIEW_PER_PAGE', 5),
        'preview_snippet_length' => (int) env('MESSAGES_PREVIEW_SNIPPET_LENGTH', 120),
        'thread_per_page' => (int) env('MESSAGES_THREAD_PER_PAGE', 30),
        'edit_window_minutes' => (int) env('MESSAGES_EDIT_WINDOW_MINUTES', 15),
    ],

    /*
    |--------------------------------------------------------------------------
    | Reviews
    |--------------------------------------------------------------------------
    |
    | `min_rate` and `max_rate` are the inclusive bounds the review Form
    | Requests enforce and the only thing that decides how many stars the widget
    | draws. The column is an unsignedTinyInteger, which accepts 0-255 and range
    | checks nothing, and the legacy app validated the rating with no rule at
    | all — `$request->rating` went straight into Review::create(), so a review
    | of 0, 99 or "abc" was storable. These bounds are that missing rule.
    |
    | Change `max_rate` and both the validator and the frontend scale move
    | together; nothing derives a column type from it.
    |
    | `max_comment_length` is the ceiling on the optional written comment,
    | against a `text` column, so it is the only limit there is — the same
    | arrangement `comments.max_length` has.
    |
    | `per_page` bounds the reviews endpoint, which pages newest first.
    |
    */

    'reviews' => [
        'min_rate' => (int) env('REVIEWS_MIN_RATE', 1),
        'max_rate' => (int) env('REVIEWS_MAX_RATE', 5),
        'max_comment_length' => (int) env('REVIEWS_MAX_COMMENT_LENGTH', 1000),
        'per_page' => (int) env('REVIEWS_PER_PAGE', 10),
    ],

    /*
    |--------------------------------------------------------------------------
    | Reports
    |--------------------------------------------------------------------------
    |
    | `max_description_length` is the ceiling on the free-text description a
    | reporter may attach. The category and the reason are closed enums
    | (ReportCategory, ReportReason) and carry no configuration.
    |
    | The reportable type is not configurable either: App\Enums\Reportable is
    | the whitelist and it is bound at the router, which is what stops a
    | request naming its own target class.
    |
    */

    'reports' => [
        'max_description_length' => (int) env('REPORTS_MAX_DESCRIPTION_LENGTH', 1000),
    ],

    /*
    |--------------------------------------------------------------------------
    | Locales
    |--------------------------------------------------------------------------
    |
    | `supported` is the whitelist every locale decision reads: the SetLocale
    | middleware, the LocaleController's validation rule, User::preferredLocale()
    | and the profile form's `locale` rule. Adding a language is this array plus
    | a `lang/{code}` directory and a `lang/{code}.json`, and nothing else.
    |
    | `rtl` lists the ones written right to left. It drives the `dir` attribute
    | the mail templates and the Inertia shared props emit; it is a list rather
    | than a hardcoded `=== 'ar'` so the third language does not need a code
    | change.
    |
    | The cookie is how a guest's choice survives, since there is no user row to
    | write it to. A year, matching the legacy LocaleManager, because a language
    | preference is not something anybody wants to re-pick every session. It is
    | in the `encryptCookies` except-list in bootstrap/app.php for the same
    | reason `appearance` is: it holds no secret and the client reads it.
    |
    */

    'locales' => [
        'supported' => ['en', 'ar'],
        'rtl' => ['ar'],
        'cookie' => 'locale',
        'cookie_minutes' => (int) env('LOCALE_COOKIE_MINUTES', 525600),
    ],

    /*
    |--------------------------------------------------------------------------
    | Profiles
    |--------------------------------------------------------------------------
    |
    | `max_avatar_kilobytes` is the ceiling the profile form enforces on the
    | uploaded avatar, in kilobytes, matching Laravel's `max` file rule. The
    | legacy form allowed 2 MB; this is deliberately the same order so an
    | existing user's photo still fits.
    |
    | `bio_max_length` bounds the free-text bio against a `text` column, so it
    | is the only limit there is — the same arrangement `comments.max_length`
    | has. The legacy rule was a bare `nullable|string` with no ceiling at all.
    |
    | `listings_per_page` and `reviews_per_page` bound the two collections the
    | public profile page ships. Listings are nine a page by decision
    | (2026-09-06): three rows of the three-column grid a visitor sees, and the
    | owner's table pages at the same size. Reviews default to the same page
    | size the reviews endpoint uses, so the first slice on the page and the
    | first page fetched from `reviews.index` line up.
    |
    | `media_directory_attempts` is how many times the registration flow will
    | redraw a colliding `media_directory_name` before giving up. The legacy
    | RegisterUserAction retried by calling itself with no bound at all, so a
    | saturated column — or any other integrity error whose message happened to
    | mention the column — was an infinite recursion ending in a stack
    | overflow. Three draws out of a 10^15..10^18 space is already far past the
    | point where a collision means something is wrong rather than unlucky.
    |
    */

    'profiles' => [
        'max_avatar_kilobytes' => (int) env('PROFILES_MAX_AVATAR_KILOBYTES', 2048),
        'bio_max_length' => (int) env('PROFILES_BIO_MAX_LENGTH', 1000),
        'listings_per_page' => (int) env('PROFILES_LISTINGS_PER_PAGE', 9),
        'reviews_per_page' => (int) env('PROFILES_REVIEWS_PER_PAGE', 10),
        'media_directory_attempts' => (int) env('PROFILES_MEDIA_DIRECTORY_ATTEMPTS', 3),
    ],

    /*
    |--------------------------------------------------------------------------
    | Notifications
    |--------------------------------------------------------------------------
    |
    | `inbox_per_page` bounds the notification inbox endpoint, which pages
    | newest first. The legacy NotificationInboxService shipped a hardcoded 20
    | rows on every single page render as a shared Inertia prop; this is a page
    | of a real paginator behind its own route, so a page that never opens the
    | bell costs no notification query at all.
    |
    */

    'notifications' => [
        'inbox_per_page' => (int) env('NOTIFICATIONS_INBOX_PER_PAGE', 15),
    ],

];
