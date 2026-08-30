<?php

namespace App\Enums;

/**
 * Delivery state of a message.
 *
 * `sent` matches the `messages.status` column default and is the only state the
 * product writes today; read position is tracked per participant on
 * `conversation_user.last_read_at`, not here. Delivery receipts land as new
 * cases.
 */
enum MessageStatus: string
{
    case Sent = 'sent';

    public function label(): string
    {
        return match ($this) {
            self::Sent => 'Sent',
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
