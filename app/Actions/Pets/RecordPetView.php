<?php

namespace App\Actions\Pets;

use App\Models\Pet;
use App\Models\User;
use Illuminate\Support\Facades\Cache;

/**
 * Count a visit to a listing, at most once per visitor per window.
 *
 * `views` is deliberately outside Pet's #[Fillable] set, so it is never
 * writable from a request and has to be incremented here. Owners looking at
 * their own listing are not counted, which is the only thing that makes the
 * number worth showing them.
 *
 * Two things the naive increment got wrong:
 *
 * 1. It bumped `updated_at`. The listing page is public and unauthenticated, so
 *    every passer-by aged a listing to "just now" — measured turning a 2020
 *    seeded row into today's on a single GET, which reorders the whole feed.
 *    withoutTimestamps() writes the counter and nothing else.
 * 2. It counted every request. A reload loop inflated the number for free.
 *    Cache::add() is atomic and returns false when the key is already there, so
 *    the first visit in the window counts and the rest do not.
 *
 * The visitor key is supplied by the caller rather than read from the request
 * here: an Action knows nothing about HTTP. Web\PetController::visitorKey()
 * builds it — a signed-in visitor by id, a guest by a hash of their IP — and
 * owns the reasoning for that choice.
 *
 * It used to key a guest by session id, and that is the one thing this Action's
 * docblock has to keep saying, because it read as a privacy win and was a hole:
 * the session id comes from a client-controlled cookie, so a caller keeping no
 * cookie jar drew a fresh key per request and the window never applied at all.
 * A `curl` loop against this public, unthrottled GET incremented `views` once
 * per request.
 *
 * `views` is still a vanity counter, not a metric — do not build anything on
 * it. A caller with a pool of addresses can inflate it, and everyone behind one
 * NAT now counts once. The window stops the honest reload loop and the key
 * stops the lazy script; neither claims more than that.
 */
class RecordPetView
{
    /**
     * @return bool Whether this visit was counted.
     */
    public function handle(Pet $pet, ?User $viewer, string $visitorKey): bool
    {
        if ($viewer !== null && $viewer->getKey() === $pet->user_id) {
            return false;
        }

        $counted = Cache::add(
            sprintf('pet-view:%s:%s', $pet->getKey(), $visitorKey),
            true,
            now()->addMinutes((int) config('petconnect.pets.view_dedup_minutes', 60)),
        );

        if (! $counted) {
            return false;
        }

        Pet::withoutTimestamps(fn () => $pet->increment('views'));

        return true;
    }
}
