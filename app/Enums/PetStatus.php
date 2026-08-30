<?php

namespace App\Enums;

use App\Concerns\HasOptions;

enum PetStatus: string
{
    use HasOptions;

    case Available = 'available';
    case Unavailable = 'unavailable';

    public function label(): string
    {
        return match ($this) {
            self::Available => 'Available',
            self::Unavailable => 'Unavailable',
        };
    }
}
