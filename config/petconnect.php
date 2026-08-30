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

];
