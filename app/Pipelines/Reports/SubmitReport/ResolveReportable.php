<?php

namespace App\Pipelines\Reports\SubmitReport;

use Closure;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\ModelNotFoundException;

/**
 * Turn the whitelisted target type and id into the model being reported.
 *
 * The counterpart of the router binding: the router refuses a
 * `{reportable_type}` that is not an App\Enums\Reportable value, and this step
 * turns what survived into a model through the enum's own class map. Nothing
 * from the request is ever used as a class name — the legacy request took
 * `reportable_type` as a free string and wrote it straight into the morph
 * column.
 *
 * Resolution goes through findVisibleOrFail(), which delegates to the target's
 * own resolveRouteBinding(). That matters here more than anywhere else in this
 * vertical: `Comment::resolveRouteBinding()` refuses to bind a comment whose
 * commentable is hidden, so a comment on a soft-deleted listing — which the
 * comment endpoints already 404 — cannot be reported either, and
 * `Review::resolveRouteBinding()` refuses a review whose target is gone. A
 * report is the one place a user hands moderation an id they read off a page,
 * so a target the rest of the app treats as absent must not become a moderation
 * row here. See App\Concerns\ResolvesMorphTarget and .ai/rules/app.md.
 *
 * A missing or hidden target is a ModelNotFoundException and therefore a 404.
 * The legacy request called `find()` and, on null, simply skipped its guards
 * and filed the report against a nonexistent row.
 *
 * @throws ModelNotFoundException<Model> When the target is gone or hidden.
 */
class ResolveReportable
{
    public function handle(SubmitReportContext $context, Closure $next): mixed
    {
        $context->setReportable(
            $context->reportableType->findVisibleOrFail($context->reportableId),
        );

        return $next($context);
    }
}
