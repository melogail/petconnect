<?php

namespace App\Nova\Filters;

use App\Enums\ReportCategory;
use BackedEnum;

/**
 * Narrow the moderation queue to one broad kind of report — abuse, copyright,
 * a bug, feedback. What the reporter said the report is *about*, as opposed to
 * ReportReasonFilter, which is what they said was *wrong*.
 */
class ReportCategoryFilter extends EnumFilter
{
    /**
     * The displayable name of the filter.
     *
     * @var \Stringable|string
     */
    public $name = 'Category';

    protected function column(): string
    {
        return 'category';
    }

    /**
     * @return class-string<BackedEnum>
     */
    protected function enum(): string
    {
        return ReportCategory::class;
    }
}
