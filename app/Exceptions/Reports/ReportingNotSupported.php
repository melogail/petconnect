<?php

namespace App\Exceptions\Reports;

use App\Contracts\Reportable as ReportableContract;
use App\Enums\Reportable as ReportableType;
use Illuminate\Database\Eloquent\Model;
use LogicException;

/**
 * The report flow resolved a target that cannot be reported.
 *
 * A programming error, never user input: App\Enums\Reportable is a closed
 * whitelist bound at the router, so reaching this means a case was added for a
 * model that never implemented App\Contracts\Reportable.
 *
 * This is the loud failure that replaces the legacy silent one. The legacy
 * StoreReportRequest skipped its self-report and duplicate guards for any
 * `reportable_type` other than Review or Comment and filed the report anyway;
 * here a type the guards cannot be run against stops the flow before a row is
 * written, and says exactly which class is missing which interface.
 *
 * A LogicException, not a ValidationException: no submitted field can fix it.
 */
class ReportingNotSupported extends LogicException
{
    public static function for(Model $reportable): self
    {
        return new self(sprintf(
            '[%s] is registered in %s but does not implement %s, so it cannot be reported.',
            $reportable::class,
            ReportableType::class,
            ReportableContract::class,
        ));
    }
}
