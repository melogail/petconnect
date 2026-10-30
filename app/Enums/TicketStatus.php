<?php

namespace App\Enums;

use App\Concerns\HasOptions;
use App\Contracts\HasLabel;

/**
 * Where a support ticket stands, read as "whose turn it is".
 *
 * `Open` — waiting on the team: the state a ticket arrives in, and the one it
 * returns to whenever the sender writes again. `Answered` — the team replied
 * and is waiting on the sender. `Closed` — finished, by either side; nothing
 * more can be written to it.
 *
 * The transitions are written in Actions\Tickets\ReplyToTicket and
 * Actions\Tickets\CloseTicket, and by App\Nova\Actions\ChangeTicketStatus for
 * an admin override. The label is English for the back office; the member's
 * pages render `support.status.*` from the client catalogue.
 */
enum TicketStatus: string implements HasLabel
{
    use HasOptions;

    case Open     = 'open';
    case Answered = 'answered';
    case Closed   = 'closed';

    public function label(): string
    {
        return match ($this) {
            self::Open     => 'Open',
            self::Answered => 'Answered',
            self::Closed   => 'Closed',
        };
    }
}
