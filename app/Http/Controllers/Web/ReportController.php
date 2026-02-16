<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreReportRequest;
use App\Actions\CreateReport;

class ReportController extends Controller
{
    /**
     * Handle the incoming request.
     */
    public function __invoke(StoreReportRequest $storeReportRequest, CreateReport $createReport)
    {

        $report = $$createReport->execute($storeReportRequest->validated());

        return back()->with('success', 'Report submitted successfully');
    }
}
