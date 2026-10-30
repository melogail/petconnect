<?php

namespace App\Nova;

use App\Models\TicketMessage as TicketMessageModel;
use App\Nova\Policies\TicketMessagePolicy;
use Laravel\Nova\Fields\BelongsTo;
use Laravel\Nova\Fields\DateTime;
use Laravel\Nova\Fields\Field;
use Laravel\Nova\Fields\ID;
use Laravel\Nova\Fields\MorphTo;
use Laravel\Nova\Fields\Textarea;
use Laravel\Nova\Http\Requests\NovaRequest;

/**
 * One message of a ticket's thread, read under its Ticket.
 *
 * Exists for the `HasMany` on App\Nova\Ticket and is kept out of the menu:
 * nobody browses messages without their ticket. Every field is read only — a
 * message is what somebody wrote, and the team's own replies are written
 * through Actions\ReplyToTicket so the status moves with them
 * (TicketMessagePolicy).
 *
 * `author` is a `MorphTo` over the two guards' models: the member who wrote
 * it or the admin who answered; empty once that account is deleted.
 *
 * @extends \App\Nova\Resource<TicketMessageModel>
 */
class TicketMessage extends Resource
{
    /**
     * The model the resource corresponds to.
     *
     * @var class-string<TicketMessageModel>
     */
    public static $model               = TicketMessageModel::class;

    /**
     * The Nova-only policy the resource corresponds to.
     *
     * @var class-string<TicketMessagePolicy>
     */
    public static $policy              = TicketMessagePolicy::class;

    /**
     * The single value that should be used to represent the resource when being displayed.
     *
     * @var string
     */
    public static $title               = 'id';

    /**
     * The logical group associated with the resource.
     *
     * @var string
     */
    public static $group               = 'Support';

    /**
     * Reached through a ticket only.
     *
     * @var bool
     */
    public static $displayInNavigation = false;

    /**
     * The columns that should be searched.
     *
     * @var array<int, string>
     */
    public static $search              = ['id', 'body'];

    /**
     * The relationships that should be eager loaded on index queries.
     *
     * @var array<int, string>
     */
    public static $with                = ['author'];

    /**
     * Get the fields displayed by the resource.
     *
     * @return array<int, Field>
     */
    public function fields(NovaRequest $request): array
    {
        return [
            ID::make()->sortable(),

            BelongsTo::make('Ticket', 'ticket', Ticket::class)
                ->readonly(),

            MorphTo::make('Author', 'author')
                ->types([User::class, Admin::class])
                ->nullable()
                ->readonly(),

            Textarea::make('Body')
                ->readonly()
                ->alwaysShow(),

            DateTime::make('Sent', 'created_at')
                ->readonly()
                ->sortable()
                ->exceptOnForms(),
        ];
    }
}
