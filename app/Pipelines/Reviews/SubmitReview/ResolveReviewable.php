<?php

namespace App\Pipelines\Reviews\SubmitReview;

use Closure;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\ModelNotFoundException;

/**
 * Turn the whitelisted target type and id into the model being reviewed.
 *
 * This step is the counterpart of the router binding, and together they are the
 * whole fix for the legacy hole. The router refuses a `{reviewable_type}` that
 * is not an App\Enums\Reviewable value; this step turns the value that survived
 * into a model through the enum's own class map. At no point does a string from
 * the request reach Eloquent as a class name, which is precisely what
 * `$type::find($request->reviewable_id)` did in the legacy controller.
 *
 * Resolution goes through findVisibleOrFail() rather than findOrFail(), so the
 * target's own answer to "may this id be addressed directly right now" applies
 * here too — global scopes, plus whatever the model records in
 * resolveRouteBinding(). Today's only Reviewable is User, which hides nothing,
 * so the two are equivalent; the day a soft-deleting model joins the whitelist
 * they are not, and this is the call that already handles it. See
 * App\Concerns\ResolvesMorphTarget.
 *
 * A missing target is a ModelNotFoundException and therefore a 404. The legacy
 * controller had no null check at all: `$type::find()` returned null and
 * CreateReviewAction dereferenced `->id` on it, so reviewing a deleted profile
 * was a 500 rather than a 404.
 *
 * @throws ModelNotFoundException<Model> When the target is gone or hidden.
 */
class ResolveReviewable
{
    public function handle(SubmitReviewContext $context, Closure $next): mixed
    {
        $context->setReviewable(
            $context->reviewableType->findVisibleOrFail($context->reviewableId),
        );

        return $next($context);
    }
}
