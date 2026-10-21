/**
 * What a support message is about: the backing values of
 * `App\Enums\TicketType`. The support page is a `Route::inertia()` route with
 * no props, so the list is not shipped from the server; it is spelled once in
 * `components/support/ticketTypes.ts`, keyed by this union, and the backend
 * `Rule::enum` is what rejects a value the two ever disagree on.
 */
export type TicketType =
    'technical_issue' | 'missing_category' | 'missing_breed' | 'other';
