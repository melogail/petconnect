<?php

namespace App\Nova\Filters;

use App\Enums\ReportStatus;
use BackedEnum;

/**
 * Narrow the moderation queue to one decision state.
 *
 * The filter a moderator lives in: "Pending" is the work list.
 */
class ReportStatusFilter extends EnumFilter
{
    /**
     * The displayable name of the filter.
     *
     * @var \Stringable|string
     */
    public $name = 'Status';

    protected function column(): string
    {
        return 'status';
    }

    /**
     * @return class-string<BackedEnum>
     */
    protected function enum(): string
    {
        return ReportStatus::class;
    }
}
