<?php

namespace App\Enums;

enum ListingType: string
{
    case Adoption = 'adoption';
    case Sale = 'sale';
    case Mating = 'mating';

    public function label(): string
    {
        return match ($this) {
            self::Adoption => 'Adoption',
            self::Sale => 'Sale',
            self::Mating => 'Mating',
        };
    }

    /**
     * Tailwind classes the frontend applies to a listing type badge.
     */
    public function style(): string
    {
        return match ($this) {
            self::Adoption => 'bg-gray-200',
            self::Sale => 'bg-green-200',
            self::Mating => 'bg-red-200',
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
