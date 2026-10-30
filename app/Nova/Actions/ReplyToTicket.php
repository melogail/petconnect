<?php

namespace App\Nova\Actions;

use App\Actions\Tickets\ReplyToTicket as ReplyToTicketAction;
use App\Models\Admin;
use App\Models\Ticket;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Laravel\Nova\Actions\Action;
use Laravel\Nova\Actions\ActionResponse;
use Laravel\Nova\Fields\ActionFields;
use Laravel\Nova\Fields\Field;
use Laravel\Nova\Fields\Textarea;
use Laravel\Nova\Http\Requests\NovaRequest;
use Throwable;

/**
 * Write the team's reply to one or more tickets.
 *
 * A thin shell over Actions\Tickets\ReplyToTicket — the same code path a
 * member's follow-up takes, so the status transition (to Answered), the
 * timestamps and the sender's notification are written in one place. What
 * this class adds is the Nova shape: a Textarea, the acting admin resolved
 * off the `admin` guard, and the whole selection in one transaction with a
 * sentence on failure (.ai/rules/nova-actions.md).
 *
 * Bulk use is for one reply that fits several tickets ("the breed is added
 * now"); each ticket gets its own message row and its own notification.
 */
class ReplyToTicket extends Action
{
    /**
     * The displayable name of the action.
     *
     * @var \Stringable|string
     */
    public $name = 'Reply to Ticket';

    /**
     * Perform the action on the given models.
     *
     * @param  Collection<int, Ticket>  $models
     */
    public function handle(ActionFields $fields, Collection $models): ActionResponse
    {
        try {
            $admin = Auth::guard(config('nova.guard'))->user();

            if (! $admin instanceof Admin) {
                return ActionResponse::danger('Nothing was sent: no admin is signed in on the Nova guard.');
            }

            $body  = trim((string) $fields->get('reply'));
            // Resolved here, not constructor-injected: Nova builds actions
            // with `static::make()` (`new static`), outside the container.
            $reply = app(ReplyToTicketAction::class);

            DB::transaction(function () use ($models, $admin, $body, $reply): void {
                $models->each(fn (Ticket $ticket) => $reply->handle($ticket, $admin, $body));
            });

            return ActionResponse::message(sprintf(
                'Replied to %d ticket(s); the sender(s) have been notified.',
                $models->count(),
            ));
        } catch (Throwable $exception) {
            report($exception);

            return ActionResponse::danger(
                'Nothing was sent. One of the selected tickets could not be replied to, so the whole selection was rolled back. The failure has been logged.',
            );
        }
    }

    /**
     * Get the fields available on the action.
     *
     * @return array<int, Field>
     */
    public function fields(NovaRequest $request): array
    {
        return [
            Textarea::make('Reply')
                ->rules('required', 'string', 'max:'.config('petconnect.support.max_message_length', 2000))
                ->help('Appended to the thread and marks the ticket Answered. The member sees it on their ticket page and by email.'),
        ];
    }
}
