<?php

namespace App\Pipelines\Reports\SubmitReport;

use App\Exceptions\Reports\AlreadyReported;
use App\Models\Report;
use Closure;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Database\UniqueConstraintViolationException;

/**
 * Write the report row.
 *
 * ## The integrity violation is where the duplicate rule is actually enforced
 *
 * `reports` carries unique (user_id, reportable_type, reportable_id), added in
 * Phase 1a precisely because the legacy app guarded duplicates only in
 * `withValidator()` — a check-then-write, which two concurrent submissions walk
 * straight through. EnsureNotAlreadyReported above is the friendly fast path;
 * this catch is the guarantee, and it converts the database's refusal into the
 * same field error rather than a 500.
 *
 * Deleting a comment also strands the reports of its whole cascaded subtree —
 * `comments.parent_id` cascades, a DB cascade fires no Eloquent events, and
 * `reports` reaches a comment through a morph column that can carry no foreign
 * key. .ai/rules/pipelines.md adds that a stranded report then collides with a
 * genuine one on a recycled id; see App\Exceptions\Reports\AlreadyReported for
 * why that second step does not hold on this schema as built (`$table->id()`
 * emits AUTOINCREMENT, verified on the live tables). The race is what justifies
 * this catch; the stranded rows are a separate, still-real problem that
 * belongs to whatever deletes the target.
 *
 * The catch is on Illuminate\Database\UniqueConstraintViolationException, a
 * QueryException subclass the connection raises for exactly this case:
 * Connection::runQueryCallback() consults the driver's own
 * isUniqueConstraintError() — `UNIQUE constraint failed:` on SQLite,
 * `Integrity constraint violation: 1062` on MySQL — and promotes the exception
 * before it leaves the connection. Matching the class covers both drivers
 * without this step naming either error code, and without a broad
 * `catch (QueryException)` that would report an unrelated failure as a
 * duplicate. `reports` has exactly one unique index, so the mapping is
 * unambiguous.
 *
 * ## Nothing off the wire becomes an identity
 *
 * `user_id`, `reportable_type` and `reportable_id` are stamped from the
 * context. All three are in Report's #[Fillable] because factories fill them,
 * so forwarding a validated bag into create() — which is what the legacy
 * CreateReport did with `$data['reportable_type']` — is how a caller files a
 * report against an arbitrary morph value under someone else's user id.
 *
 * `status` is not written at all: it is outside #[Fillable], the column
 * defaults to `pending`, and Report mirrors that default in $attributes so the
 * returned instance reads back as ReportStatus::Pending without a refresh.
 * Moving a report off Pending is a moderator Action's job (Phase 3).
 *
 * `metadata` is left null. The column exists and the factory fills it with
 * `{ip_address, user_agent}`, but capturing a reporter's IP is a privacy
 * decision this vertical was not asked to make, and a pipeline step is the
 * wrong place to read a request in any case — it would have to arrive on the
 * context from the controller. Raised as an open question rather than decided
 * in passing.
 *
 * No transaction: one INSERT is already atomic.
 *
 * @throws AlreadyReported When the unique index refuses the row.
 */
class PersistReport
{
    public function handle(SubmitReportContext $context, Closure $next): mixed
    {
        $reportable = $context->reportable();

        try {
            $report = Report::create([
                'user_id' => $context->reporter->getKey(),
                'reportable_type' => Relation::getMorphAlias($reportable::class),
                'reportable_id' => $reportable->getKey(),
                'category' => $context->category,
                'reason' => $context->reason,
                'description' => $context->description,
            ]);
        } catch (UniqueConstraintViolationException) {
            throw AlreadyReported::make();
        }

        $context->setReport($report);

        return $next($context);
    }
}
