<?php

namespace App\Traits;

use App\Models\Report;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\MorphMany;

trait HasReport
{
    public function reports(): MorphMany
    {
        return $this->morphMany(Report::class, 'reportable');
    }

    public function scopeWithReportedByCurrentUser(Builder $query): Builder
    {
        if (! auth()->check()) {
            return $query;
        }

        return $query->withExists([
            'reports as has_reported_by_current_user' => fn (Builder $reportQuery) => $reportQuery
                ->where('user_id', auth()->id()),
        ]);
    }
}
