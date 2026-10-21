<?php

namespace App\Enums;

use App\Concerns\HasOptions;

/**
 * What a support message is about.
 *
 * Backing values are machine-safe snake_case and are what `contacts.ticket_type`
 * stores; the English label is for the back office. The support form itself
 * renders its own translated labels from `support.ticket_type.*` in the client
 * catalogue, keyed by these values, so a reader in either locale sees their
 * own language — this enum is not shipped to the page.
 */
enum TicketType: string
{
    use HasOptions;

    case TechnicalIssue  = 'technical_issue';
    case MissingCategory = 'missing_category';
    case MissingBreed    = 'missing_breed';
    case Other           = 'other';

    public function label(): string
    {
        return match ($this) {
            self::TechnicalIssue  => 'Technical issue',
            self::MissingCategory => 'Missing pet category',
            self::MissingBreed    => 'Missing pet breed',
            self::Other           => 'Other',
        };
    }
}
