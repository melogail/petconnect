<?php

namespace App\Enums;

use App\Concerns\HasOptions;

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
    use HasOptions;

    case Sent = 'sent';

    public function label(): string
    {
        return match ($this) {
            self::Sent => 'Sent',
        };
    }
}
