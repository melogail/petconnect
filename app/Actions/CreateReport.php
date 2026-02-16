<?php

namespace App\Actions;

use App\Models\Report;
use App\Enums\ReportCategory;
use App\Enums\ReportReason;

class CreateReport
{
    public function execute($data)
    {
        return Report::create([
            'user_id' => auth()->id(),
            'category' => $data['category'] ?? ReportCategory::other,
            'reason' => $data['reason'] ?? ReportReason::other,
            'description' => $data['description'],

        ]);
    }
}
