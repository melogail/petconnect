<?php

namespace App\Enums;

/**
 * The shape of a message thread.
 *
 * `direct` is the only type the product creates today and matches the
 * `conversations.type` column default. Group threads land as a new case, never
 * as a raw string.
 */
enum ConversationType: string
{
    case Direct = 'direct';

    public function label(): string
    {
        return match ($this) {
            self::Direct => 'Direct',
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
