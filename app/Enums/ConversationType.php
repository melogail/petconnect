<?php

namespace App\Enums;

use App\Concerns\HasOptions;

/**
 * The shape of a message thread.
 *
 * `direct` is the only type the product creates today and matches the
 * `conversations.type` column default. Group threads land as a new case, never
 * as a raw string.
 */
enum ConversationType: string
{
    use HasOptions;

    case Direct = 'direct';

    public function label(): string
    {
        return match ($this) {
            self::Direct => 'Direct',
        };
    }
}
