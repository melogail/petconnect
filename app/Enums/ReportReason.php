<?php

namespace App\Enums;

use App\Concerns\HasOptions;

enum ReportReason: string
{
    use HasOptions;

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
}
