<?php

namespace App\Nova\Metrics;

use App\Models\Report;
use DateTimeInterface;
use Laravel\Nova\Http\Requests\NovaRequest;
use Laravel\Nova\Metrics\Value;
use Laravel\Nova\Metrics\ValueResult;

/**
 * How many reports are still waiting for a decision, right now.
 *
 * The single most useful number in this back office and the reason the
 * moderation dashboard exists. It is an outstanding-work figure, not a
 * rate: a report filed eight months ago and never triaged is exactly the one
 * that should be counted, so this must not be constrained to a date window.
 * That is why it computes directly through the model's own `pending` scope
 * instead of `$this->count($request, ...)`, whose rangeless fallback is one
 * day (see TotalUsers for the full explanation).
 */
class PendingReports extends Value
{
    /**
     * The element's icon.
     *
     * @var string
     */
    public $icon = 'flag';

    /**
     * Calculate the value of the metric.
     */
    public function calculate(NovaRequest $request): ValueResult
    {
        return $this->result(Report::query()->pending()->count())->allowZeroResult();
    }

    /**
     * Get the ranges available for the metric.
     *
     * @return array<int|string, string>
     */
    public function ranges(): array
    {
        return [];
    }

    /**
     * Determine the amount of time the results of the metric should be cached.
     */
    public function cacheFor(): ?DateTimeInterface
    {
        return null;
    }

    /**
     * Get the URI key for the metric.
     */
    public function uriKey(): string
    {
        return 'pending-reports';
    }
}
