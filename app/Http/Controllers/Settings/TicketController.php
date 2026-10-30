<?php

namespace App\Http\Controllers\Settings;

use App\Actions\Tickets\CloseTicket;
use App\Actions\Tickets\ListUserTickets;
use App\Actions\Tickets\OpenTicket;
use App\Actions\Tickets\ReplyToTicket;
use App\Concerns\ResolvesRequestUser;
use App\Concerns\TicketValidationRules;
use App\Http\Controllers\Controller;
use App\Http\Requests\Tickets\ReplyToTicketRequest;
use App\Http\Requests\Tickets\StoreTicketRequest;
use App\Http\Resources\Ticket\TicketMessageResource;
use App\Http\Resources\Ticket\TicketResource;
use App\Models\Ticket;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * The member's side of the support desk, inside the settings shell: the list
 * of their tickets, one ticket's thread, and the two things they can do to a
 * ticket — write a follow-up and close it.
 *
 * `show`, `reply` and `close` act on a policy-governed model and authorize
 * against TicketPolicy. `index` lists the caller's own rows and `store` opens
 * a ticket for the caller, which the `auth` middleware already establishes —
 * there is no second party whose access is in question, the exemption
 * .ai/rules/controllers.md allows. Tickets are members-only: a guest's message
 * is `Web\ContactController`.
 *
 * `ticketBounds` rides on both pages so the composers' `maxlength` is the
 * server's rule rather than a number typed into a component
 * (.ai/rules/concerns.md).
 */
class TicketController extends Controller
{
    use ResolvesRequestUser;
    use TicketValidationRules;

    public function index(Request $request, ListUserTickets $listUserTickets): Response
    {
        $user = $this->actor($request);

        return Inertia::render('settings/Tickets', [
            'tickets'      => TicketResource::collection($listUserTickets->handle($user)),
            'ticketBounds' => $this->ticketBounds(),
        ]);
    }

    public function store(StoreTicketRequest $request, OpenTicket $openTicket): RedirectResponse
    {
        $ticket = $openTicket->handle(
            sender: $this->actor($request),
            category: $request->category(),
            subject: $request->subject(),
            message: $request->message(),
        );

        Inertia::flash('toast', [
            'type'    => 'success',
            'message' => __('Your ticket has been opened. We will get back to you shortly.'),
        ]);

        return to_route('tickets.show', $ticket);
    }

    public function show(Request $request, Ticket $ticket): Response
    {
        $this->authorize('view', $ticket);

        $ticket->loadMissing('messages.author');

        return Inertia::render('settings/Ticket', [
            'ticket'       => TicketResource::make($ticket),
            'messages'     => TicketMessageResource::collection($ticket->messages),
            'canReply'     => $this->actor($request)->can('reply', $ticket),
            'ticketBounds' => $this->ticketBounds(),
        ]);
    }

    public function reply(ReplyToTicketRequest $request, Ticket $ticket, ReplyToTicket $replyToTicket): RedirectResponse
    {
        $this->authorize('reply', $ticket);

        $replyToTicket->handle($ticket, $this->actor($request), $request->body());

        return back();
    }

    public function close(Request $request, Ticket $ticket, CloseTicket $closeTicket): RedirectResponse
    {
        $this->authorize('close', $ticket);

        $closeTicket->handle($ticket, $this->actor($request));

        Inertia::flash('toast', [
            'type'    => 'success',
            'message' => __('Your ticket has been closed. Thank you for letting us know.'),
        ]);

        return back();
    }
}
