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

];
