<?php

namespace App\Enums;

enum PetStatus: string
{
    case Available = 'available';
    case Unavailable = 'unavailable';

    public function label(): string
    {
        return match ($this) {
            self::Available => 'Available',
            self::Unavailable => 'Unavailable',
        };
    }

    /**
     * Tailwind classes the frontend applies to a pet status badge.
     */
    public function color(): string
    {
        return match ($this) {
            self::Available => 'bg-green-100 text-green-800 dark:bg-green-900/40 dark:text-green-300',
            self::Unavailable => 'bg-yellow-100 text-yellow-800 dark:bg-yellow-900/40 dark:text-yellow-300',
        };
    }

    /**
     * @return array<int, array{value: string, label: string}>
     */
    public static function options(): array
    {
        return array_map(
            fn (self $case): array => ['value' => $case->value, 'label' => $case->label()],
            self::cases(),
        );
    }
}
