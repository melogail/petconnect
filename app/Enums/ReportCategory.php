<?php

namespace App\Enums;

enum ReportCategory: string
{
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

    /**
     * @return array<int, array{value: string, label: string}>
     */
    public static function options(): array
    {
        return array_map(
            fn (self $case): array => ['value' => $case->value, 'label' => $case->label()],
            self::cases()
        );
    }
}
