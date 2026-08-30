<?php

namespace App\Enums;

enum ReportReason: string
{
    case Spam = 'spam';
    case HateSpeech = 'hate_speech';
    case FalseInformation = 'false_information';
    case Violation = 'violation';
    case InappropriateContent = 'inappropriate_content';
    case Other = 'other';

    public function label(): string
    {
        return match ($this) {
            self::Spam => 'Spam',
            self::HateSpeech => 'Hate Speech',
            self::FalseInformation => 'False Information',
            self::Violation => 'Violation',
            self::InappropriateContent => 'Inappropriate Content',
            self::Other => 'Other',
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
