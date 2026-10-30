<?php

namespace App\Enums;

use App\Concerns\HasOptions;
use App\Contracts\HasLabel;

/**
 * What a support ticket is about, chosen by the sender when opening it.
 *
 * Backing values are machine-safe snake_case and are what `tickets.category`
 * stores; the English label is for the back office. The ticket form renders
 * its own translated labels from `support.category.*` in the client
 * catalogue, keyed by these values, so a reader in either locale sees their
 * own language — this enum is not shipped to the page.
 */
enum TicketCategory: string implements HasLabel
{
    use HasOptions;

    case TechnicalIssue  = 'technical_issue';
    case AccountIssue    = 'account_issue';
    case ListingIssue    = 'listing_issue';
    case MissingCategory = 'missing_category';
    case MissingBreed    = 'missing_breed';
    case Feedback        = 'feedback';
    case Other           = 'other';

    public function label(): string
    {
        return match ($this) {
            self::TechnicalIssue  => 'Technical issue',
            self::AccountIssue    => 'Account issue',
            self::ListingIssue    => 'Listing issue',
            self::MissingCategory => 'Missing pet category',
            self::MissingBreed    => 'Missing pet breed',
            self::Feedback        => 'Feedback',
            self::Other           => 'Other',
        };
    }
}
