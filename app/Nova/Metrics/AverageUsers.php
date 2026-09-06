<?php

namespace App\Nova\Metrics;

use App\Models\User;
use DateTimeInterface;
use Illuminate\Support\Carbon;
use Laravel\Nova\Http\Requests\NovaRequest;
use Laravel\Nova\Metrics\Value;
use Laravel\Nova\Metrics\ValueResult;

/**
 * Average sign-ups per day across the whole life of the `users` table.
 *
 * ## What this replaces
 *
 * The legacy metric of the same name computed the reciprocal of what it
 * claimed, from two backwards queries, and fatalled on an empty table:
 *
 *     $startOfRecords = User::query()->orderBy('created_at', 'desc')->first()->created_at;
 *     $endOfRecords   = User::query()->orderBy('created_at', 'asc')->first()->created_at;
 *     $totalDays      = $endOfRecords->diffInDays($startOfRecords);
 *     $averageUsersPerDay = $totalDays / User::count();
 *
 * Three separate faults. `$startOfRecords` ordered `created_at` **desc**, so
 * it held the *newest* row while being named the oldest, and `$endOfRecords`
 * held the oldest — the two are swapped. The division is then
 * days ÷ users, i.e. **days per user**, and the variable it is assigned to is
 * called `$averageUsersPerDay`; on a dataset of 100 users over 50 days it
 * printed 0.50 where the honest answer is 2.00, and the wrongness is invisible
 * because both numbers are plausible. And `->first()->created_at` had no null
 * guard, so the very first page load of a fresh install — zero users — was a
 * fatal on null, as was any dataset that would divide by zero.
 *
 * ## What it does now
 *
 * One aggregate query for the first and last `created_at`, an explicit zero
 * for the empty table, users ÷ days (the right way up), and a floor of one day
 * so that a table whose rows all landed today reports the day's count rather
 * than dividing by zero.
 *
 * ## Why it does not use $this->count($request, ...)
 *
 * This is an all-time figure, and Nova's ranged helpers are not. With
 * `ranges()` empty the front end sends no `range` parameter, and
 * Value::aggregate() then falls back to `$range = 1` — one *day*. Routing an
 * "all time" metric through the helper would silently make it "since
 * yesterday". Computing it directly is what keeps the label true. The same
 * applies to TotalUsers; read that class.
 */
class AverageUsers extends Value
{
    /**
     * The element's icon.
     *
     * @var string
     */
    public $icon = 'user-group';

    /**
     * Calculate the value of the metric.
     */
    public function calculate(NovaRequest $request): ValueResult
    {
        /** @var object{first: string|null, last: string|null}|null $span */
        $span = User::query()
            ->selectRaw('min(created_at) as first, max(created_at) as last')
            ->first();

        $total = User::query()->count();

        if ($total === 0 || $span?->first === null || $span->last === null) {
            return $this->result(0)->allowZeroResult()->format('0,0.00');
        }

        $days = max(1.0, (float) Carbon::parse($span->first)->diffInDays(Carbon::parse($span->last)));

        return $this->result(round($total / $days, 2))
            ->allowZeroResult()
            ->format('0,0.00')
            ->suffix('per day')
            ->withoutSuffixInflection();
    }

    /**
     * Get the ranges available for the metric.
     *
     * Deliberately empty: the figure spans every row there has ever been, so
     * there is nothing to select between. Nova renders no range control when
     * this is empty (`v-if="ranges.length > 0"` in BaseValueMetric.vue), so
     * the card is a plain number rather than a dropdown with nothing in it.
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
        return 'average-users';
    }
}
