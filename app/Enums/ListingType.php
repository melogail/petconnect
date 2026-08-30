<?php

namespace App\Enums;

use App\Concerns\HasOptions;

enum ListingType: string
{
    use HasOptions;

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
}
