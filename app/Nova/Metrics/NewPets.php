<?php

namespace App\Nova\Metrics;

use App\Models\Pet;
use DateTimeInterface;
use Laravel\Nova\Http\Requests\NovaRequest;
use Laravel\Nova\Metrics\Value;
use Laravel\Nova\Metrics\ValueResult;
use Laravel\Nova\Nova;

/**
 * Listings published within the selected window.
 *
 * Counted through `Pet::query()` rather than the class name, so the model's
 * SoftDeletes global scope applies and retired listings are excluded — a
 * "new listings" figure that counted rows an owner has already deleted would
 * overstate supply, which is the number this card exists to show.
 */
class NewPets extends Value
{
    /**
     * The element's icon.
     *
     * @var string
     */
    public $icon = 'sparkles';

    /**
     * Calculate the value of the metric.
     */
    public function calculate(NovaRequest $request): ValueResult
    {
        return $this->count($request, Pet::query())->allowZeroResult();
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
            365 => Nova::__('365 Days'),
            'MTD' => Nova::__('Month To Date'),
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
        return 'new-pets';
    }
}
