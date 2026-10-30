# Taxonomy and client options

## Purpose
Categories (7) and breeds (82) that classify every listing, plus the enum option lists and numeric bounds the client needs to render forms and filters.

## Data
`categories`, `breeds` (both bilingual name/description, slug, optional image in media collections `categories` / `breeds`). Seed JSON in `database/data/categories.json`, `breeds.json`; seeders `CategorySeeder`, `BreedSeeder`.

## Backend
- `app/Actions/Pets/ListPetCategories.php` returns the category → breed tree.
- `app/Actions/Options/BuildClientOptions.php` returns listing_types, statuses, genders, health_statuses, report_categories, report_reasons, bounds (filters, photos, comments, reviews, messages, profiles) and locales.
- Enum `options()` comes from `App\Concerns\HasOptions`; labels implement `HasLabel`.
- Controllers: `Api/V1/CategoryController`, `Api/V1/OptionController`. On the web the same data is shared through Inertia props (`HandleInertiaRequests`) and deferred `categories` on Home.
- Image validation for taxonomy images: `TaxonomyImageRules`.

## Routes
`GET api/v1/categories`, `GET api/v1/options`.

## Frontend
`components/pets/filter/CategorySelection.vue`, category icons in `categoryIcon.ts`, select inputs in the pet form.

## Admin
Nova `Category` and `Breed` resources (images via advanced-nova-media-library); `DeleteCategory` action guards against categories that still have pets (`restrictOnDelete`).

## Tests
`tests/Feature/Seeders/`, `tests/Unit/Enums/`, `tests/Feature/Nova/`.

## Open work
- Users request missing categories/breeds through support tickets (`TicketCategory::MissingCategory/MissingBreed`); there is no self-serve path, which is intended.
