<?php

namespace App\Nova\Filters;

use App\Enums\ReportReason;
use BackedEnum;

/**
 * Narrow the moderation queue to one stated reason — spam, hate speech, false
 * information and so on. The complaint itself, as opposed to
 * ReportCategoryFilter's broader bucket.
 */
class ReportReasonFilter extends EnumFilter
{
    /**
     * The displayable name of the filter.
     *
     * @var \Stringable|string
     */
    public $name = 'Reason';

    protected function column(): string
    {
        return 'reason';
    }

    /**
     * @return class-string<BackedEnum>
     */
    protected function enum(): string
    {
        return ReportReason::class;
    }
}
