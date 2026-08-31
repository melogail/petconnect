<?php

namespace App\Nova\Filters;

use BackedEnum;
use Illuminate\Contracts\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Laravel\Nova\Filters\Filter;
use Laravel\Nova\Http\Requests\NovaRequest;

/**
 * A select filter over a column backed by one of the application's enums.
 *
 * Extracted rather than written three times. Every enum in App\Enums stores a
 * snake_case backing value and renders through its own `label()` (see
 * .ai/rules/enums.md), so "filter this column by that enum" is one behaviour
 * with two parameters — the column and the enum — and a subclass supplies
 * exactly those. Adding a fourth enum filter is a subclass, not another copy
 * of `apply()`; that is the open/closed half of it.
 *
 * The option *keys* are the human labels and the option *values* are what
 * reaches `apply()`, which is Nova's convention and the reverse of what the
 * name suggests.
 */
abstract class EnumFilter extends Filter
{
    /**
     * The filter's component.
     *
     * @var string
     */
    public $component = 'select-filter';

    /**
     * The column this filter constrains.
     */
    abstract protected function column(): string;

    /**
     * The enum whose cases are the available options.
     *
     * Every case must expose `label()`; App\Concerns\HasOptions declares it
     * abstract, so any enum using that trait qualifies.
     *
     * @return class-string<BackedEnum>
     */
    abstract protected function enum(): string;

    /**
     * Apply the filter to the given query.
     *
     * @param  Builder<Model>  $query
     * @return Builder<Model>
     */
    public function apply(NovaRequest $request, Builder $query, mixed $value): Builder
    {
        return $query->where($this->column(), $value);
    }

    /**
     * Get the filter's available options.
     *
     * @return array<string, string>
     */
    public function options(NovaRequest $request): array
    {
        $enum = $this->enum();

        return collect($enum::cases())
            ->mapWithKeys(fn (BackedEnum $case): array => [$case->label() => $case->value])
            ->all();
    }
}
