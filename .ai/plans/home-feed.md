# Home feed

## Purpose
The landing page: an infinite-scroll grid of available pets with filters and a "near me" mode.

## Data
Reads `pets` (scope `available`), `categories`, `breeds`. Geo uses `pets.latitude/longitude` with a Haversine `nearby` scope (Earth radius 6371 km) and `withDistance` for ordering.

## Backend
- `app/Actions/Pets/ListHomeFeedPets.php` runs the `BuildHomeFeed` pipeline: ScopeToAvailablePets → ApplyCategoryAndBreedFilter → ApplyListingTypeFilter → ApplyAgeRangeFilter → ApplyVaccinatedFilter → ApplySearchFilter → ApplyNearbyOrRecency → EagerLoadFeedRelations → PaginateFeed.
- Request: `Pet/ListHomeFeedRequest` accepts `search`, `category_ids[]`, `breed_ids[]`, `listing_types[]`, `age_min`, `age_max`, `vaccinated`, `latitude`, `longitude`, `radius`. Bounds (radius 1–100 km, default 20; age 0–15 years) come from `config/petconnect.php`.
- `Web/HomeController` renders `Home` with `pets` as `Inertia::scroll()` (merged pages) and `categories` as `Inertia::defer()` (only needed when the filter sheet opens).

## Routes
`GET /` (home). API equivalent `GET api/v1/pets` with the same query parameters.

## Frontend
`pages/Home.vue`; `components/pets/filter/` (CategorySelection, TaxonomyQuery, CheckboxState, categoryIcon), `NearbySearchButton` (uses browser geolocation and the `bounds` prop), pet cards.

## Guards
Public. No throttle on the feed itself.

## Tests
`tests/Feature/Http/Controllers/Web/HomeControllerTest.php`, `tests/Feature/Actions/Pets/`.

## Open work
- Home copy is feed-generic ("Discover Pets"); an Arabic value proposition for first-time visitors is still missing.
- With 22 seeded cities the feed is thin outside Greater Cairo; consider defaulting the location filter to the user's city.
