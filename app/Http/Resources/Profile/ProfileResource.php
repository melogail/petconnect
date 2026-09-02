<?php

namespace App\Http\Resources\Profile;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * A user as their own public page renders them.
 *
 * The read side of `profile.show`, which is reachable without an account, so
 * **everything here is public by construction**.
 *
 * ## What the legacy resource leaked, and is not here
 *
 * The legacy ProfileResource emitted `email`, `phone`, `address`, `lat` and
 * `lng` on a route explicitly marked public. A profile page therefore published
 * the subject's email address, phone number, street address and exact
 * coordinates to anybody with the link. None of those are below: `location` is
 * the coarse "City, State, Country" accessor, and the contact fields exist only
 * on ProfileFormResource, which is served to the account holder alone.
 *
 * It also emitted `is_verified`, reading a property that does not exist on the
 * model, so the key was always `false`. `is_verified` here comes from
 * User::isVerified().
 *
 * ## Why this is not UserSummaryResource
 *
 * UserSummaryResource is the single *byline* payload — id, name, username,
 * location, avatar — and it stays that. This is the page about the person, so
 * it adds the bio, the join date, the rating summary and the viewer's
 * relationship to it. It is deliberately a separate class rather than a
 * widening of the summary, because widening the summary would put the bio and
 * the rating average beside every comment on every feed card.
 *
 * ## Counts and flags fall back rather than lazy load
 *
 * `reviews_count`, `reviews_avg_rate`, `pets_count`, `is_liked` and
 * `has_reviewed` are read with `??` and never through a relation, so a loader
 * that forgets one ships a neutral value instead of an N+1 — and on a
 * single-model result set `preventLazyLoading` would not have caught it anyway
 * (see .ai/rules/app.md). Actions\Profiles\LoadProfileForDisplay adds all five
 * as subqueries on the query it is already issuing; `reviews_count` and
 * `reviews_avg_rate` come from HasReviews::withReviewStats().
 *
 * The avatar is read with getFirstMediaUrl(), so whoever loads the user must
 * eager load `media`. It asks for the `display` conversion, which is only a
 * real answer because `User::registerMediaConversions()` generates that one
 * inline — a queued conversion would never be generated here (no worker runs)
 * and `getFirstMediaUrl()` would quietly hand back the raw upload instead.
 * Read that method's docblock before changing either end.
 *
 * ## The two viewer-relative flags, and what each is for
 *
 * `is_liked` finally has a write endpoint behind it. It shipped for two phases
 * with nothing that could flip it — `pets.like` and `comments.like` existed and
 * a profile like did not — and `profile.like` is now that route, running the
 * same Actions\Likes\ToggleLike as the other two.
 *
 * `has_reviewed` is new, and it is the one fact the review form needed and did
 * not have. A second review of the same person by the same author is refused by
 * a unique index and by SubmitReview\EnsureNotAlreadyReviewed, but the page
 * could not tell: it offered the form to everybody and explained afterwards
 * through `errors.review`. Both flags are false for a guest, which is the
 * honest answer — neither question means anything without a viewer.
 *
 * ## `last_seen_at` is gone
 *
 * It was emitted here and read by nobody, and it could not have been trusted if
 * it had been: **no code path in the application ever writes the column**. Only
 * UserFactory and UserSeeder set it, so in production every profile would have
 * published a null, and in a seeded environment a fabricated one. It is also
 * the kind of key that is hard to remove later — a public "last seen" is a
 * presence disclosure that needs its own decision, not a column that leaked
 * onto a payload — so it comes off now. The column stays; if presence is ever
 * a feature, it arrives with a writer and a choice about who may see it.
 *
 * @mixin User
 */
class ProfileResource extends JsonResource
{
    /**
     * @return array{
     *     id: int,
     *     name: string,
     *     username: string|null,
     *     bio: string|null,
     *     location: string,
     *     avatar: string|null,
     *     is_verified: bool,
     *     is_self: bool,
     *     is_liked: bool,
     *     has_reviewed: bool,
     *     pets_count: int,
     *     reviews_count: int,
     *     reviews_avg_rate: float|null,
     *     can_update: bool,
     *     created_at: mixed
     * }
     */
    public function toArray(Request $request): array
    {
        $viewer = $request->user();

        return [
            'id' => $this->id,
            'name' => $this->name,
            'username' => $this->username,
            'bio' => $this->bio,
            'location' => $this->location,
            'avatar' => $this->getFirstMediaUrl('users', 'display') ?: null,

            'is_verified' => $this->isVerified(),
            'is_self' => $viewer?->getKey() === $this->getKey(),
            'is_liked' => (bool) ($this->is_liked ?? false),
            'has_reviewed' => (bool) ($this->has_reviewed ?? false),

            'pets_count' => (int) ($this->pets_count ?? 0),
            'reviews_count' => (int) ($this->reviews_count ?? 0),
            'reviews_avg_rate' => $this->reviews_avg_rate === null
                ? null
                : round((float) $this->reviews_avg_rate, 2),

            'can_update' => (bool) $viewer?->can('update', $this->resource),

            'created_at' => $this->created_at,
        ];
    }
}
