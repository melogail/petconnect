<?php

namespace App\Http\Resources\Review;

use App\Models\Review;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * A review, wherever it is rendered.
 *
 * The single review payload in the application.
 *
 * ## The review form contract
 *
 * Both writable keys are emitted under exactly the name the Form Requests
 * accept, so round-tripping a review into an edit box is a straight assignment:
 *
 * | emitted   | StoreReviewRequest              | UpdateReviewRequest                      |
 * |-----------|---------------------------------|------------------------------------------|
 * | `rate`    | required, integer, min 1, max 5 | required, integer, min 1, max 5          |
 * | `comment` | nullable, string, max           | present, nullable, string, max           |
 *
 * The bounds come from `petconnect.reviews.*` through
 * App\Concerns\ReviewValidationRules, which is the single place either key is
 * spelled — and therefore the file most likely to break this parity. See
 * .ai/rules/resources.md.
 *
 * `rate`, not `rating`. The legacy ReviewResource emitted `rating` while the
 * column, the model property and (had there been one) the write key were all
 * `rate`, so every consumer had to remember which side of the wire it was on.
 * One name, matching the column.
 *
 * Everything else is a read shape with no write counterpart: `id`, `author`,
 * `is_author`, `has_reported`, `can_edit`, `can_delete`, `created_at`,
 * `updated_at`.
 *
 * ## `has_reported` falls back rather than lazy loading
 *
 * It comes from the `withReportedBy()` withExists() subquery on whatever query
 * loaded the review, is read with `??`, and is never reached through the
 * relation — so a loader that omits it produces `false` instead of an N+1, and,
 * on a single-row result set, instead of a lazy load Model::preventLazyLoading()
 * would not even catch (see .ai/rules/app.md). It is absent for a guest,
 * because `withReportedBy()` is a no-op for a null viewer.
 *
 * ## `author` disappears rather than lazy loading, and nothing catches that
 *
 * `whenLoaded('user')` means a loader that forgets `user` ships reviews with no
 * `author` key at all. Measured on a 10-review page: 2 queries instead of 4, no
 * exception, a payload a quarter smaller. Half-forgetting — `with('user')`
 * without `media` — *is* loud, because getFirstMediaUrl() then lazy loads
 * `media` and Model::preventLazyLoading() throws. So the complete mistake is
 * the silent one, and a test protecting the eager load has to assert the
 * `author` key is present rather than assert a query count. See
 * Actions\Reviews\ListReviews.
 *
 * ## The two `can_*` keys are asked per row, so the policy must not query
 *
 * ReviewPolicy::update and ::delete decide from `user_id` and `isVerified()`
 * alone — attributes already on the models in hand — so asking them once per
 * rendered review costs nothing. That is a constraint on the policy, not a
 * coincidence: a participation or ownership check added there would become one
 * query per row on a page that renders ten of them, and Gate calls are
 * invisible to preventLazyLoading. It is stated in ReviewPolicy's docblock too.
 * See .ai/rules/policies.md.
 *
 * The legacy resource emitted the same pair as a nested `can: {update, delete}`
 * object; it is flattened here to match MessageResource's `can_edit` /
 * `can_delete`, which is the shape the frontend already reads elsewhere.
 *
 * `created_at` is emitted as the raw timestamp, not `diffForHumans()`. The
 * legacy resource rendered "3 days ago" server-side, which freezes the reader's
 * locale and the moment of rendering into the payload — the same mistake
 * .ai/rules/notifications.md records against storing rendered text. The client
 * formats it.
 *
 * @mixin Review
 */
class ReviewResource extends JsonResource
{
    /**
     * @return array{
     *     id: int,
     *     rate: int,
     *     comment: string|null,
     *     author: mixed,
     *     is_author: bool,
     *     has_reported: bool,
     *     can_edit: bool,
     *     can_delete: bool,
     *     created_at: mixed,
     *     updated_at: mixed
     * }
     */
    public function toArray(Request $request): array
    {
        $viewer = $request->user();

        return [
            'id' => $this->id,
            'rate' => $this->rate,
            'comment' => $this->comment,

            'author' => ReviewAuthorResource::make($this->whenLoaded('user')),
            'is_author' => $viewer?->getKey() === $this->user_id,

            'has_reported' => (bool) ($this->has_reported ?? false),

            'can_edit' => (bool) $viewer?->can('update', $this->resource),
            'can_delete' => (bool) $viewer?->can('delete', $this->resource),

            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
