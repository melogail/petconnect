<?php

namespace App\Enums;

enum ReportReason: string
{
    case span = 'Spam';
    case hateSpeech = 'Hate Speech';
    case falseInformation = 'False Information';
    case violation = 'Violation';
    case inappropriateContent = 'Inappropriate Content';
    case other = 'Other';

    public function label(): string
    {
        return match ($this) {
            self::span => 'Spam',
            self::hateSpeech => 'Hate Speech',
            self::falseInformation => 'False Information',
            self::violation => 'Violation',
            self::inappropriateContent => 'Inappropriate Content',
            self::other => 'Other',
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
