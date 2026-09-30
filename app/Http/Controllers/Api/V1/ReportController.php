<?php

namespace App\Http\Controllers\Api\V1;

use App\Actions\Reports\CreateReport;
use App\Concerns\ResolvesRequestUser;
use App\Enums\Reportable;
use App\Http\Controllers\Controller;
use App\Http\Requests\Report\StoreReportRequest;
use App\Models\Report as ReportModel;
use Illuminate\Http\JsonResponse;

/**
 * File a report from the app. The report itself is never returned — the web
 * never shows it either; moderation reads it in Nova.
 */
class ReportController extends Controller
{
    use ResolvesRequestUser;

    public function store(
        StoreReportRequest $request,
        Reportable $reportable_type,
        int $reportable_id,
        CreateReport $createReport,
    ): JsonResponse {
        $this->authorize('create', ReportModel::class);

        $createReport->handle(
            reporter: $this->actor($request),
            reportableType: $reportable_type,
            reportableId: $reportable_id,
            category: $request->category(),
            reason: $request->reason(),
            description: $request->description(),
        );

        return response()->json(['message' => __('Report submitted.')], 201);
    }
}
