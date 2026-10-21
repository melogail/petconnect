import type { TicketType } from '@/types';

/**
 * The topics the contact form offers, in the order the select lists them.
 *
 * Each value is a backing value of `App\Enums\TicketType` and each label is a
 * catalogue key, so the select reads in the viewer's locale — the enum's own
 * `label()` is English only (.ai/rules/enums.md). The page carries no props
 * (`Route::inertia()`), which is why this is a constant rather than an option
 * list handed down the way `ReportDialog` receives its categories.
 *
 * `satisfies` keeps the list total over the union: dropping a member or
 * misspelling a value is a type error here, and a value the backend does not
 * know is a 422 from its `Rule::enum`.
 */
export const TICKET_TYPES = [
    {
        value: 'technical_issue',
        labelKey: 'support.ticket_type.technical_issue',
    },
    {
        value: 'missing_category',
        labelKey: 'support.ticket_type.missing_category',
    },
    { value: 'missing_breed', labelKey: 'support.ticket_type.missing_breed' },
    { value: 'other', labelKey: 'support.ticket_type.other' },
] as const satisfies readonly { value: TicketType; labelKey: string }[];
