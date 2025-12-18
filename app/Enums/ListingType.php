<?php

namespace App\Enums;

enum ListingType: int
{
    case Adoption = 0;
    case Sale     = 1;
    case Mating  = 2;

    public function label(): string
    {
        return match ($this) {
            self::Adoption => 'Adoption',
            self::Sale => 'Sale',
            self::Mating => 'Mating',
        };
    }

    public function style(): string
    {
        return match ($this) {
            self::Adoption => 'bg-gray-200',
            self::Sale => 'bg-green-200',
            self::Mating => 'bg-red-200',
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
