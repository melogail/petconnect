<?php

namespace App\Nova\Metrics;

use App\Models\User;
use DateTimeInterface;
use Laravel\Nova\Http\Requests\NovaRequest;
use Laravel\Nova\Metrics\Value;
use Laravel\Nova\Metrics\ValueResult;
use Laravel\Nova\Nova;

/**
 * Sign-ups within the selected window, against the window before it.
 *
 * The one user metric that genuinely wants ranges, so it is the one that uses
 * Nova's ranged `count()` helper: the range the admin picks is the date
 * predicate, and the previous-period comparison Nova draws underneath the
 * number is meaningful. TotalUsers and AverageUsers deliberately do not use
 * the helper — read those classes for why.
 *
 * `allowZeroResult()` so a quiet week shows "0" rather than Nova's "No Data"
 * placeholder; zero sign-ups is a result, not a missing one.
 */
class NewUsers extends Value
{
    /**
     * The element's icon.
     *
     * @var string
     */
    public $icon = 'user-plus';

    /**
     * Calculate the value of the metric.
     */
    public function calculate(NovaRequest $request): ValueResult
    {
        return $this->count($request, User::class)->allowZeroResult();
    }

    /**
     * Get the ranges available for the metric.
     *
     * @return array<int|string, string>
     */
    public function ranges(): array
    {
        return [
            'TODAY' => Nova::__('Today'),
            7 => Nova::__('7 Days'),
            30 => Nova::__('30 Days'),
            60 => Nova::__('60 Days'),
            365 => Nova::__('365 Days'),
            'MTD' => Nova::__('Month To Date'),
            'QTD' => Nova::__('Quarter To Date'),
            'YTD' => Nova::__('Year To Date'),
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
        return 'new-users';
    }
}
