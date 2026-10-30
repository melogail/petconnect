# Support tickets

## Purpose
Authenticated users open a ticket, admins answer from Nova, users reply or close from settings; replies email the user.

## Data
`tickets` (user_id nullable, name, email, category, subject, status, last_activity_at, closed_at), `ticket_messages` (ticket_id, author_type/author_id morph to User or Admin, body). Enums TicketCategory, TicketStatus.

## Backend
- Actions (`app/Actions/Tickets/`): OpenTicket, ReplyToTicket, CloseTicket, ListUserTickets.
- `TicketPolicy`, `TicketValidationRules`, requests under `Tickets/`, resources under `Ticket/`.
- `TicketRepliedNotification` (database + mail) when support answers. `TicketMessage::isFromSupport()` drives the bubble style.
- Controller `Settings/TicketController` (index, store, show, reply, close).

## Routes
`GET|POST settings/tickets`, `GET settings/tickets/{ticket}`, `POST settings/tickets/{ticket}/reply`, `POST settings/tickets/{ticket}/close`. Auth + verified.

## Frontend
`pages/settings/{Tickets,Ticket}.vue`, status badge map in `components/support/TICKET_STATUS_VARIANT`.

## Guards
Throttle `ticket-messages` (5/min). Users only see their own tickets; closed tickets reject replies.

## Admin
Nova `Ticket` and `TicketMessage` resources; actions `ReplyToTicket`, `ChangeTicketStatus`; `TicketPolicy` / `TicketMessagePolicy` under `app/Nova/Policies`.

## Tests
`tests/Feature/Settings/`, `tests/Feature/Nova/Actions/`.

## Open work
- No API routes for tickets, so the mobile app cannot open one.
- No SLA or auto-close of stale answered tickets.
