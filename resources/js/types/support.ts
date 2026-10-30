/** `App\Enums\TicketStatus` backing values — read as "whose turn it is". */
export type TicketStatus = 'open' | 'answered' | 'closed';

/** `App\Enums\TicketCategory` backing values; rendered via `support.category.*`. */
export type TicketCategory =
    | 'technical_issue'
    | 'account_issue'
    | 'listing_issue'
    | 'missing_category'
    | 'missing_breed'
    | 'feedback'
    | 'other';

/**
 * A ticket's header — `App\Http\Resources\Ticket\TicketResource`, served to
 * the ticket's own sender on `tickets.index` and `tickets.show`. The thread
 * is `TicketMessage[]`, shipped as its own prop on the show page only.
 */
export type Ticket = {
    id: number;
    category: TicketCategory;
    subject: string;
    status: TicketStatus;
    /** Thread length; 0 where the loader did not count (the show page). */
    messages_count: number;
    last_activity_at: string | null;
    closed_at: string | null;
    created_at: string | null;
};

/** One message of a thread — `App\Http\Resources\Ticket\TicketMessageResource`. */
export type TicketMessage = {
    id: number;
    body: string;
    /** True for the team's replies; decides which side the bubble sits on. */
    is_from_support: boolean;
    /** Null for a guest's opening message or an author whose account is gone. */
    author_name: string | null;
    created_at: string | null;
};

/** `ticketBounds` — the server's composer limits, so `maxlength` is the rule. */
export type TicketBounds = {
    max_message_length: number;
};
