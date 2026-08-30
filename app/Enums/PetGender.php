<?php

namespace App\Enums;

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
    case Male = 'male';
    case Female = 'female';

    public function label(): string
    {
        return match ($this) {
            self::Male => 'Male',
            self::Female => 'Female',
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
