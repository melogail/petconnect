<?php

namespace App\Enums;

use App\Concerns\HasOptions;

enum ReportStatus: string
{
    use HasOptions;

    case Pending = 'pending';
    case Reviewed = 'reviewed';
    case Resolved = 'resolved';
    case Rejected = 'rejected';

    public function label(): string
    {
        return match ($this) {
            self::Pending => 'Pending',
            self::Reviewed => 'Reviewed',
            self::Resolved => 'Resolved',
            self::Rejected => 'Rejected',
        };
    }
}
