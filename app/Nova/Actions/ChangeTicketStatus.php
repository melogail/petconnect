<?php

namespace App\Nova\Actions;

use App\Actions\Tickets\CloseTicket;
use App\Enums\TicketStatus;
use App\Models\Admin;
use App\Models\Ticket;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Laravel\Nova\Actions\Action;
use Laravel\Nova\Actions\ActionResponse;
use Laravel\Nova\Fields\ActionFields;
use Laravel\Nova\Fields\Field;
use Laravel\Nova\Fields\Select;
use Laravel\Nova\Http\Requests\NovaRequest;
use Throwable;

/**
 * Move one or more tickets to a status without writing to the thread.
 *
 * Closing goes through Actions\Tickets\CloseTicket, the one writer of
 * `closed_at`, which also tells the sender. Open and Answered are set here
 * directly: they are the team correcting whose turn it is ("we answered this
 * by phone", "they came back to us in person") and notify nobody, because
 * nothing was said.
 *
 * Same shape as ChangeReportStatus — one Select over the enum so a new case
 * needs no new class, the selection in one transaction, a sentence on failure
 * (.ai/rules/nova-actions.md).
 */
class ChangeTicketStatus extends Action
{
    /**
     * The displayable name of the action.
     *
     * @var \Stringable|string
     */
    public $name = 'Change Status';

    /**
     * Perform the action on the given models.
     *
     * @param  Collection<int, Ticket>  $models
     */
    public function handle(ActionFields $fields, Collection $models): ActionResponse
    {
        try {
            $admin  = Auth::guard(config('nova.guard'))->user();

            if (! $admin instanceof Admin) {
                return ActionResponse::danger('Nothing was changed: no admin is signed in on the Nova guard.');
            }

            $status = TicketStatus::from((string) $fields->get('status'));
            // Resolved here, not constructor-injected: Nova builds actions
            // with `static::make()` (`new static`), outside the container.
            $close  = app(CloseTicket::class);

            DB::transaction(function () use ($models, $admin, $status, $close): void {
                $models->each(function (Ticket $ticket) use ($admin, $status, $close): void {
                    if ($status === TicketStatus::Closed) {
                        $close->handle($ticket, $admin);

                        return;
                    }

                    $ticket->status           = $status;
                    $ticket->closed_at        = null;
                    $ticket->last_activity_at = now();
                    $ticket->save();
                });
            });

            return ActionResponse::message(sprintf(
                '%d ticket(s) marked as %s.',
                $models->count(),
                $status->label(),
            ));
        } catch (Throwable $exception) {
            report($exception);

            return ActionResponse::danger(
                'Nothing was changed. One of the selected tickets could not be moved to the new status, so the whole selection was rolled back. The failure has been logged.',
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
            Select::make('Status')
                ->options(TicketStatus::class)
                ->displayUsingLabels()
                ->default(TicketStatus::Closed->value)
                ->rules('required', Rule::enum(TicketStatus::class))
                ->help('Open is waiting on us, Answered is waiting on the sender, Closed is finished. Closing tells the sender; to say something, use Reply to Ticket instead.'),
        ];
    }
}
