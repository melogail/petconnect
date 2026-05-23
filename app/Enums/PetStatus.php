<?php

namespace App\Enums;

enum PetStatus: string
{
    case available = 'available';
    case unavailable = 'unavailable';

    public function label(): string
    {
        return match ($this) {
            self::available => 'Available',
            self::unavailable => 'Unavailable',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::available => 'bg-green-100 text-green-800 dark:bg-green-900/40 dark:text-green-300',
            self::unavailable => 'bg-yellow-100 text-yellow-800 dark:bg-yellow-900/40 dark:text-yellow-300',
        };
    }
}
