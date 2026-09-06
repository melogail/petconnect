<?php

namespace App\Nova\Metrics;

use App\Models\User;
use DateTimeInterface;
use Laravel\Nova\Http\Requests\NovaRequest;
use Laravel\Nova\Metrics\Value;
use Laravel\Nova\Metrics\ValueResult;

/**
 * Every registered member account, all time.
 *
 * ## The decision about `ranges()`
 *
 * It stays empty, and the metric stays a `Value`. Nova renders no range
 * control when the array is empty — `BaseValueMetric.vue` guards the
 * SelectControl with `v-if="ranges.length > 0"` — so a rangeless Value is a
 * plain "big number" card, which is exactly the right shape for a total. There
 * is no empty dropdown to remove.
 *
 * ## The bug that was actually there
 *
 * The legacy metric had the same empty `ranges()` **and** delegated to
 * `$this->count($request, User::class)`, which is where it went wrong.
 * `Value::aggregate()` short-circuits to a plain unfiltered aggregate only
 * when `$request->range === 'ALL'`; otherwise it does
 * `$range = $request->range ?? 1` and constrains `created_at` to that many
 * days. With no range control the front end sends no `range`, so the fallback
 * fired and the card labelled "Total Users" was really "users who signed up in
 * the last day", complete with a growth arrow against the day before. On a
 * seeded database it reads 0 while the table holds hundreds.
 *
 * So the fix is not the ranges — it is not going through the ranged helper at
 * all. A total is one count with no date predicate, and that is what this now
 * is. AverageUsers has the identical trap and the same resolution.
 *
 * NewUsers is the metric that *should* be ranged, and is.
 */
class TotalUsers extends Value
{
    /**
     * The element's icon.
     *
     * @var string
     */
    public $icon = 'users';

    /**
     * Calculate the value of the metric.
     */
    public function calculate(NovaRequest $request): ValueResult
    {
        return $this->result(User::query()->count())->allowZeroResult();
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
        return 'total-users';
    }
}
