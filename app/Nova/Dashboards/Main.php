<?php

namespace App\Nova\Dashboards;

use App\Nova\Metrics\AverageUsers;
use App\Nova\Metrics\NewPets;
use App\Nova\Metrics\NewUsers;
use App\Nova\Metrics\PendingReports;
use App\Nova\Metrics\TotalUsers;
use Laravel\Nova\Card;
use Laravel\Nova\Dashboards\Main as Dashboard;

/**
 * The landing dashboard: the state of the marketplace at a glance.
 *
 * PendingReports leads, because outstanding moderation work is the one number
 * on this page that asks somebody to do something. The rest are context.
 *
 * The default `Laravel\Nova\Cards\Help` card is gone; it is a link to Nova's
 * own documentation and says nothing about this application.
 */
class Main extends Dashboard
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
            TotalUsers::make(),
            NewUsers::make(),
            AverageUsers::make(),
            NewPets::make(),
        ];
    }
}
