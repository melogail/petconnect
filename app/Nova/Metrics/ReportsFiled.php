<?php

namespace App\Nova\Metrics;

use App\Models\Report;
use DateTimeInterface;
use Laravel\Nova\Http\Requests\NovaRequest;
use Laravel\Nova\Metrics\Trend;
use Laravel\Nova\Metrics\TrendResult;
use Laravel\Nova\Nova;

/**
 * Reports filed per day.
 *
 * The incoming rate, next to PendingReports' outstanding balance: together
 * they say whether the queue is being kept up with. A Trend is the one metric
 * type here whose ranges are load-bearing, so it has them.
 */
class ReportsFiled extends Trend
{
    /**
     * The element's icon.
     *
     * @var string
     */
    public $icon = 'chart-bar';

    /**
     * Calculate the value of the metric.
     */
    public function calculate(NovaRequest $request): TrendResult
    {
        return $this->countByDays($request, Report::class)->showLatestValue();
    }

    /**
     * Get the ranges available for the metric.
     *
     * @return array<int, string>
     */
    public function ranges(): array
    {
        return [
            7 => Nova::__('7 Days'),
            30 => Nova::__('30 Days'),
            60 => Nova::__('60 Days'),
            90 => Nova::__('90 Days'),
        ];
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
        return 'reports-filed';
    }
}
