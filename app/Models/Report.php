<?php

namespace App\Models;

use App\Enums\ReportCategory;
use App\Enums\ReportReason;
use Illuminate\Database\Eloquent\Model;

class Report extends Model
{
    protected $guarded = [];

    protected $casts = [
        'category' => ReportCategory::class,
        'reason' => ReportReason::class,
    ];

}
