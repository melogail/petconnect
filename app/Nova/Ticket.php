<?php

namespace App\Nova;

use App\Concerns\TicketValidationRules;
use App\Enums\TicketCategory;
use App\Enums\TicketStatus;
use App\Models\Ticket as TicketModel;
use App\Nova\Actions\ChangeTicketStatus;
use App\Nova\Actions\ReplyToTicket;
use App\Nova\Policies\TicketPolicy;
use Laravel\Nova\Actions\Action;
use Laravel\Nova\Fields\Badge;
use Laravel\Nova\Fields\BelongsTo;
use Laravel\Nova\Fields\DateTime;
use Laravel\Nova\Fields\Field;
use Laravel\Nova\Fields\HasMany;
use Laravel\Nova\Fields\ID;
use Laravel\Nova\Fields\Select;
use Laravel\Nova\Fields\Text;
use Laravel\Nova\Http\Requests\NovaRequest;

/**
 * The support desk: every ticket, with its thread underneath.
 *
 * Every field is read only. A ticket is what somebody wrote, so there is no
 * create form and no update form (TicketPolicy); the team works a ticket with
 * two actions — Actions\ReplyToTicket, which appends to the thread and marks
 * it Answered, and Actions\ChangeTicketStatus, for closing or reopening
 * without writing. Both notify the member. Tickets are members-only; a
 * visitor's one-off message is the ContactMessage resource beside this one.
 *
 * The queue is the Open badge in the menu (NovaServiceProvider::menu) and the
 * Status filter here: Open is "waiting on us", Answered is "waiting on them".
 *
 * `user` is the account that opened it; a row whose account has since been
 * deleted keeps the name and address copied at the time, and the team's
 * replies go to that address.
 *
 * @extends \App\Nova\Resource<TicketModel>
 */
class Ticket extends Resource
{
    use TicketValidationRules;

    /**
     * The model the resource corresponds to.
     *
     * @var class-string<TicketModel>
     */
    public static $model  = TicketModel::class;

    /**
     * The Nova-only policy the resource corresponds to.
     *
     * @var class-string<TicketPolicy>
     */
    public static $policy = TicketPolicy::class;

    /**
     * The single value that should be used to represent the resource when being displayed.
     *
     * @var string
     */
    public static $title  = 'subject';

    /**
     * The logical group associated with the resource.
     *
     * @var string
     */
    public static $group  = 'Support';

    /**
     * The columns that should be searched.
     *
     * @var array<int, string>
     */
    public static $search = [
        'id', 'name', 'email', 'subject',
    ];

    /**
     * The relationships that should be eager loaded on index queries.
     *
     * @var array<int, string>
     */
    public static $with   = ['user'];

    /**
     * Get the fields displayed by the resource.
     *
     * @return array<int, Field>
     */
    public function fields(NovaRequest $request): array
    {
        return [
            ID::make()->sortable(),

            BelongsTo::make('Member', 'user', User::class)
                ->nullable()
                ->readonly(),

            Text::make('Name')
                ->rules($this->senderNameRules())
                ->readonly()
                ->sortable(),

            Text::make('Email')
                ->rules($this->senderEmailRules())
                ->readonly()
                ->copyable(),

            Select::make('Category')
                ->options(self::enumOptions(TicketCategory::class))
                ->rules($this->categoryRules())
                ->displayUsingLabels()
                ->readonly()
                ->sortable()
                ->filterable(),

            Text::make('Subject')
                ->rules($this->subjectRules())
                ->readonly()
                ->sortable(),

            Badge::make('Status')
                ->map([
                    TicketStatus::Open->value     => 'warning',
                    TicketStatus::Answered->value => 'success',
                    TicketStatus::Closed->value   => 'info',
                ])
                ->labels(self::enumOptions(TicketStatus::class))
                ->sortable()
                ->filterable(),

            DateTime::make('Opened', 'created_at')
                ->readonly()
                ->sortable()
                ->exceptOnForms(),

            DateTime::make('Last activity', 'last_activity_at')
                ->readonly()
                ->sortable()
                ->exceptOnForms(),

            DateTime::make('Closed', 'closed_at')
                ->readonly()
                ->onlyOnDetail(),

            HasMany::make('Messages', 'messages', TicketMessage::class),
        ];
    }

    /**
     * Get the actions available for the resource.
     *
     * @return array<int, Action>
     */
    public function actions(NovaRequest $request): array
    {
        return [
            ReplyToTicket::make(),
            ChangeTicketStatus::make(),
        ];
    }

    /**
     * An enum's options keyed by backing value, as Select and Badge want them.
     *
     * @param  class-string<TicketCategory|TicketStatus>  $enum
     * @return array<string, string>
     */
    private static function enumOptions(string $enum): array
    {
        return array_column($enum::options(), 'label', 'value');
    }
}
