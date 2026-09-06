<?php

namespace App\Enums;

use App\Concerns\HasOptions;

enum ReportCategory: string
{
    use HasOptions;

    case Abuse = 'abuse';
    case Bug = 'bug';
    case Copyright = 'copyright';
    case Technical = 'technical';
    case Feedback = 'feedback';
    case Other = 'other';

    public function label(): string
    {
        return match ($this) {
            self::Abuse => 'Abuse',
            self::Bug => 'Bug',
            self::Copyright => 'Copyright',
            self::Technical => 'Technical',
            self::Feedback => 'Feedback',
            self::Other => 'Other',
        };
    }
}
