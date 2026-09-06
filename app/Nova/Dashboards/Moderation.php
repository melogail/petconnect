<?php

namespace App\Nova\Dashboards;

use App\Nova\Metrics\PendingReports;
use App\Nova\Metrics\ReportsByStatus;
use App\Nova\Metrics\ReportsFiled;
use Laravel\Nova\Card;
use Laravel\Nova\Dashboard;

/**
 * The queue, in three numbers: how much is outstanding, what shape the backlog
 * is in, and how fast new work is arriving.
 *
 * Separate from Main so the moderation view is not diluted by growth metrics.
 * Everything on it is unranged except the trend, which is the only one whose
 * ranges mean anything — see App\Nova\Metrics\TotalUsers for why a rangeless
 * Value must not go through Nova's ranged helpers.
 */
class Moderation extends Dashboard
{
    /**
     * Get the cards for the dashboard.
     *
     * @return array<int, Card>
     */
    public function cards(): array
    {
        return [
            PendingReports::make(),
            ReportsByStatus::make(),
            ReportsFiled::make(),
        ];
    }

    /**
     * Get the URI key for the dashboard.
     */
    public function uriKey(): string
    {
        return 'moderation';
    }
}
