<?php

namespace App\Enums;

use App\Concerns\HasOptions;

/**
 * The sex of a listed pet.
 *
 * Closed set, so it is an enum rather than a `Rule::in([...])` literal in the
 * Form Request: the same values are needed by the validation rules, by the
 * model cast and by the form's select options, and three copies of a literal
 * list drift.
 */
enum PetGender: string
{
    use HasOptions;

    case Male = 'male';
    case Female = 'female';

    public function label(): string
    {
        return match ($this) {
            self::Male => 'Male',
            self::Female => 'Female',
        };
    }
}
