<?php

namespace App\Actions;

use App\Enums\ReportCategory;
use App\Enums\ReportReason;
use App\Models\Report;

class CreateReport
{
    public function handle(array $data): Report
    {
        return Report::create([
            'user_id' => auth()->id(),
            'category' => ReportCategory::tryFrom($data['category'] ?? '') ?? ReportCategory::other,
            'reason' => ReportReason::tryFrom($data['reason']) ?? ReportReason::other,
            'description' => $data['description'] ?? null,
            'reportable_type' => $data['reportable_type'],
            'reportable_id' => $data['reportable_id'],
        ]);
    }
}
