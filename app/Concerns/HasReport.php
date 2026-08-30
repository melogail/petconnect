<?php

namespace App\Concerns;

use App\Models\Report;
use App\Models\User;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\MorphMany;

/**
 * Gives a model a polymorphic collection of user reports filed against it.
 */
trait HasReport
{
    /**
     * @return MorphMany<Report, $this>
     */
    public function reports(): MorphMany
    {
        return $this->morphMany(Report::class, 'reportable');
    }

    /**
     * Flag each result with `has_reported` for the given user.
     *
     * The acting user is passed in rather than read from auth() so the scope
     * behaves the same in queue and console contexts; a null user is a no-op.
     *
     * @param  Builder<static>  $query
     * @return Builder<static>
     */
    #[Scope]
    protected function withReportedBy(Builder $query, ?User $user): Builder
    {
        if ($user === null) {
            return $query;
        }

        return $query->withExists([
            'reports as has_reported' => fn (Builder $reportQuery): Builder => $reportQuery
                ->where('user_id', $user->getKey()),
        ]);
    }
}
