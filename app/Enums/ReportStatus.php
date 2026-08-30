<?php

namespace App\Enums;

enum ReportStatus: string
{
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

    /**
     * Tailwind classes the frontend applies to a report status badge.
     */
    public function style(): string
    {
        return match ($this) {
            self::Pending => 'bg-gray-200',
            self::Reviewed => 'bg-blue-200',
            self::Resolved => 'bg-green-200',
            self::Rejected => 'bg-red-200',
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
