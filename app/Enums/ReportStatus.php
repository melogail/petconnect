<?php

namespace App\Enums;

use App\Concerns\HasOptions;
use App\Contracts\HasLabel;

enum ReportStatus: string implements HasLabel
{
    use HasOptions;

    case Pending  = 'pending';
    case Reviewed = 'reviewed';
    case Resolved = 'resolved';
    case Rejected = 'rejected';

    public function label(): string
    {
        return match ($this) {
            self::Pending  => 'Pending',
            self::Reviewed => 'Reviewed',
            self::Resolved => 'Resolved',
            self::Rejected => 'Rejected',
        };
    }
}
