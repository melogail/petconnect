import type { BadgeVariants } from '@/components/ui/badge';
import type { TicketStatus } from '@/types';

/**
 * The one mapping from a ticket's status to its badge treatment, shared by
 * the list row and the ticket page so the two never disagree. Open is the
 * lavender wash (waiting on the team), Answered is solid brand (your turn),
 * Closed is the quiet outline.
 */
export const TICKET_STATUS_VARIANT: Record<
    TicketStatus,
    NonNullable<BadgeVariants['variant']>
> = {
    open: 'secondary',
    answered: 'default',
    closed: 'outline',
};
