<?php

namespace App\Http\Controllers\Web;

use App\Actions\CreateReport;
use App\Http\Controllers\Controller;
use App\Http\Requests\StoreReportRequest;

class ReportController extends Controller
{
    /**
     * Handle the incoming request.
     */
    public function __invoke(StoreReportRequest $storeReportRequest, CreateReport $createReport)
    {

        $report = $createReport->handle($storeReportRequest->validated());

        return back()->with('success', __('flash.report_submitted'));
    }
}
