<?php

namespace App\Enums;

enum ReportCategory: string
{
    case abuse = 'Abuse';
    case bug = 'Bug';
    case copyright = 'Copyright';
    case technical = 'Technical';
    case feedback = 'Feedback';
    case other = 'Other';

    public function label(): string
    {
        return match ($this) {
            self::abuse => 'Abuse',
            self::bug => 'Bug',
            self::copyright => 'Copyright',
            self::technical => 'Technical',
            self::feedback => 'Feedback',
            self::other => 'Other',
        };
    }

    public static function options(): array
    {
        return array_map(
            fn ($case) => ['value' => $case->value, 'label' => $case->label()],
            self::cases()
        );
    }
}
