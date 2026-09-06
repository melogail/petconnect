<?php

namespace App\Enums;

use App\Concerns\HasOptions;

/**
 * The payload a message carries.
 *
 * `text` is the only type the product sends today and matches the
 * `messages.type` column default. Attachments land as a new case.
 */
enum MessageType: string
{
    use HasOptions;

    case Text = 'text';

    public function label(): string
    {
        return match ($this) {
            self::Text => 'Text',
        };
    }
}
