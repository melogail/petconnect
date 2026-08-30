<?php

namespace App\Enums;

/**
 * The payload a message carries.
 *
 * `text` is the only type the product sends today and matches the
 * `messages.type` column default. Attachments land as a new case.
 */
enum MessageType: string
{
    case Text = 'text';

    public function label(): string
    {
        return match ($this) {
            self::Text => 'Text',
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
