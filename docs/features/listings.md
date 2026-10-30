# Listings

A listing is a `Pet` row published by a member. Three listing types share one form and one card.

| Type | Price | Typical supplier |
| --- | --- | --- |
| `adoption` | none | shelters, rescues, owners rehoming |
| `sale` | required | breeders, pet shops |
| `mating` | optional | pedigree owners |

## Lifecycle

```
create (auth + verified) ─▶ available ─▶ unavailable (toggle) ─▶ soft delete
                                 ▲              │
                                 └──────────────┘
```

- Publishing, editing, status toggling and deleting require a verified account and ownership (`PetPolicy`).
- `status` is toggled with `PATCH pets/{pet}/status`; an unavailable listing stays visible to its owner only.
- Delete is a soft delete. Nova's `PurgePetListing` is the irreversible path and walks comments, likes, saves, reports and media.
- Each view increments `views`.

## Addressing

Listings are addressed by `slug`, assigned by `PetObserver::creating()` from the name plus a random suffix. `pets/{pet}` and `api/v1/pets/{pet}` both bind on the slug; numeric ids return 404. The route pattern is pinned by `Pet::ROUTE_KEY_PATTERN`.

## The wizard

`resources/js/pages/pets/Create.vue` and `Edit.vue` share an 8-step wizard:

1. Basics: category, breed, listing type, name, gender, birth date, litter counts
2. Description and price
3. Location: city/state/country, map pin, optional detailed address
4. Health: status, vaccinated, spayed/neutered, special needs, last vet visit
5. Vaccinations, medications, allergies (repeatable rows), vet contact
6. Traits
7. Additional info
8. Photos: one required featured image on create, optional gallery; compressed client-side before upload

Step 1 is validated on the client (`validateBasicStep`) and the mobile app mirrors that logic. Server rules live in the Form Requests and are the source of truth; `/api/v1/options` publishes the limits so clients can match them.

## Server flow

`StorePet` / `UpdatePet` Actions run the Pets pipeline (see [Backend layers](/architecture/backend#controller-action-pipeline)): resolve category and breed, normalise attributes, location, health, traits and extra info, verify photos decode, attach media, return the refreshed model.

Routes carry `throttle:pet-listings` (create) and `throttle:pet-listing-edits` (update), sized for the upload and decode work each request does. Image conversions themselves are queued (`QUEUE_CONVERSIONS_BY_DEFAULT=true`) and only run inline on the `sync` queue driver, which is what the test suite uses.

## Discovery

The home feed (`HomeController@index`) supports:

- Category and breed filters (`CategorySelection`, `TaxonomyQuery` components)
- Listing type, gender, status filters
- City filter from the seeded location list
- **Near me**: a radius selector requests geolocation, then filters by bounding box and lat/long index; the chosen radius is kept in the URL

`api/v1/pets` exposes the same filters for the mobile app.

## Media

Featured and gallery collections through medialibrary with conversions for cards and detail views. Paths are grouped per owner directory. Uploads that GD cannot decode are rejected before storage.
