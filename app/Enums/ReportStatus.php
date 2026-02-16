<?php

namespace App\Enum;

enum ReportStatus: string
{
    case pending = 'Pending';
    case reviewed = 'Reviewed';
    case resolved = 'Resolved';
    case rejected = 'Rejected';

    public function label(): string
    {
        return match ($this) {
            self::pending => 'Pending',
            self::reviewed => 'Reviewed',
            self::resolved => 'Resolved',
            self::rejected => 'Rejected',
        };
    }

    public function style(): string
    {
        return match ($this) {
            self::pending => 'bg-gray-200',
            self::reviewed => 'bg-blue-200',
            self::resolved => 'bg-green-200',
            self::rejected => 'bg-red-200',
        };
    }

    public static function options(): array
    {
        return array_map(
            fn($case) => ['value' => $case->value, 'label' => $case->label()],
            self::cases()
        );
    }

}
