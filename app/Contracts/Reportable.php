<?php

namespace App\Contracts;

use App\Models\Report;
use App\Models\User;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Support\Collection;

/**
 * A model that users are able to report to moderation.
 *
 * App\Concerns\HasReport supplies reports() and the withReportedBy() scope;
 * implementing this interface is what makes the model a legal target of the
 * submit flow, mirroring App\Contracts\Commentable / App\Concerns\HasComments.
 *
 * ## This interface is half of the second security fix in this vertical
 *
 * The legacy StoreReportRequest ran its self-report and duplicate guards inside
 * an `if (! in_array($reportableType, [Review::class, Comment::class], true))`
 * early return, so both checks were skipped for every other value — and
 * `reportable_type` was validated as `['required', 'string']`, so every other
 * value was reachable. Widening the whitelist there meant remembering to widen
 * the guard too, and nothing connected the two.
 *
 * Here the guards cannot be type-specific: Pipelines\Reports\SubmitReport\
 * EnsureNotSelfReport and EnsureNotAlreadyReported are written against this
 * interface, so a new case on App\Enums\Reportable either implements it and is
 * checked, or does not implement it and raises
 * App\Exceptions\Reports\ReportingNotSupported before a row can be written.
 * There is no third outcome and no branch to forget.
 */
interface Reportable
{
    /**
     * @return MorphMany<Report, static>
     */
    public function reports(): MorphMany;

    /**
     * The users answerable for this content — its author, its owner.
     *
     * They may not report it (SubmitReport\EnsureNotSelfReport). The legacy
     * check was `isset($reportable->user_id) && $reportable->user_id ===
     * auth()->id()`, which is a duck-typed guess: any reportable without a
     * `user_id` column silently passed it, and any reportable whose `user_id`
     * meant something other than "author" silently failed it. Asking the model
     * makes the answer the model's own.
     *
     * Returning an empty collection is a valid answer and means "nobody owns
     * this, so nobody is excluded from reporting it".
     *
     * @return Collection<int, User>
     */
    public function reportSubjects(): Collection;
}
