# Saves (bookmarks)

## Purpose
Let a user bookmark a pet to find it again later.

## Data
`saves` (user_id, saveable_type, saveable_id). `Save` model, `HasSaves` concern on Pet, `User::saves()`; `SaveSeeder` seeds rows. Purge pipelines delete saves when a listing or account is removed.

## Backend
Model and concern only. There is no action, controller, policy, resource or route for creating, removing or listing saves.

## Routes
None.

## Frontend
None.

## Tests
Seeder coverage only.

## Open work (the actual plan)
1. Add `App\Actions\Saves\ToggleSave` mirroring `ToggleLike`.
2. Add `POST pets/{pet}/save` on web and API, throttle `pet-saves` 30/min, auth required.
3. Add a "Saved" tab on the profile or a `GET me/saves` endpoint returning `PetCardResource`.
4. Save button on pet card and detail; count is not needed on the card.
5. Pest tests for toggle, unauthenticated rejection and purge cascade.
