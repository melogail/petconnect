<?php

namespace App\Nova\Metrics;

use App\Enums\ReportStatus;
use App\Models\Report;
use DateTimeInterface;
use Laravel\Nova\Http\Requests\NovaRequest;
use Laravel\Nova\Metrics\Partition;
use Laravel\Nova\Metrics\PartitionResult;

/**
 * The shape of the moderation queue: how many reports sit in each state.
 *
 * A Partition rather than a Value because the useful question is the ratio —
 * a backlog is "mostly pending", a healthy queue is "mostly resolved" — and a
 * Partition has no ranges to get wrong.
 *
 * The group-by column holds the enum's snake_case backing value, so the labels
 * are mapped back through `ReportStatus::label()`; without that the card reads
 * "pending" and "hate_speech"-style raw values.
 */
class ReportsByStatus extends Partition
{
    /**
     * Calculate the value of the metric.
     */
    public function calculate(NovaRequest $request): PartitionResult
    {
        return $this->count($request, Report::class, groupBy: 'status')
            ->label(fn (?string $value): string => $value === null
                ? 'Unknown'
                : ReportStatus::from($value)->label())
            ->colors([
                ReportStatus::Pending->value => '#f59e0b',
                ReportStatus::Reviewed->value => '#3b82f6',
                ReportStatus::Resolved->value => '#22c55e',
                ReportStatus::Rejected->value => '#ef4444',
            ]);
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
        return 'reports-by-status';
    }
}
