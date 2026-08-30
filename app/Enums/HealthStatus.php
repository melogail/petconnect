<?php

namespace App\Enums;

/**
 * The general health of a listed pet, as the pet form's health group reports it.
 *
 * Closed set, so it is an enum rather than a `Rule::in([...])` literal in the
 * Form Request: the same values are needed by the validation rules, by the
 * model cast and by the form's select options, and three copies of a literal
 * list drift.
 */
enum HealthStatus: string
{
    case Healthy = 'healthy';
    case MinorIssues = 'minor_issues';
    case ChronicCondition = 'chronic_condition';

    public function label(): string
    {
        return match ($this) {
            self::Healthy => 'Healthy',
            self::MinorIssues => 'Minor issues',
            self::ChronicCondition => 'Chronic condition',
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
