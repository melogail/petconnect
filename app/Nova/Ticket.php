<?php

namespace App\Nova;

use App\Concerns\ContactValidationRules;
use App\Enums\TicketType;
use App\Models\Contact as ContactModel;
use App\Nova\Policies\ContactPolicy;
use Laravel\Nova\Fields\BelongsTo;
use Laravel\Nova\Fields\DateTime;
use Laravel\Nova\Fields\Field;
use Laravel\Nova\Fields\ID;
use Laravel\Nova\Fields\Select;
use Laravel\Nova\Fields\Text;
use Laravel\Nova\Fields\Textarea;
use Laravel\Nova\Http\Requests\NovaRequest;

/**
 * The support inbox: messages sent through the support page's contact form.
 *
 * Every field is read only. A message is what somebody wrote, so there is no
 * create form and no update form (ContactPolicy); the admin reads it, replies
 * from their own mail client to the address on the row, and deletes it when it
 * is dealt with. Unlike a report, a contact message carries no decision and
 * nothing hangs off it, so Nova's ordinary delete is the right tool here and
 * no DestructiveAction stands in for it.
 *
 * `user` is the account that sent it, when there was one; a guest's row has
 * none and shows the name and address they typed instead.
 *
 * @extends \App\Nova\Resource<ContactModel>
 */
class Contact extends Resource
{
    use ContactValidationRules;

    /**
     * The model the resource corresponds to.
     *
     * @var class-string<ContactModel>
     */
    public static $model  = ContactModel::class;

    /**
     * The Nova-only policy the resource corresponds to.
     *
     * @var class-string<ContactPolicy>
     */
    public static $policy = ContactPolicy::class;

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
        'id', 'name', 'email', 'subject', 'message',
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
                ->rules($this->contactNameRules())
                ->readonly()
                ->sortable(),

            Text::make('Email')
                ->rules($this->contactEmailRules())
                ->readonly()
                ->copyable(),

            Select::make('Ticket type', 'ticket_type')
                ->options(self::ticketTypeOptions())
                ->rules($this->ticketTypeRules())
                ->displayUsingLabels()
                ->readonly()
                ->sortable()
                ->filterable(),

            Text::make('Subject')
                ->rules($this->subjectRules())
                ->readonly()
                ->sortable(),

            Textarea::make('Message')
                ->rules($this->contactMessageRules())
                ->readonly()
                ->alwaysShow(),

            DateTime::make('Sent', 'created_at')
                ->readonly()
                ->sortable()
                ->exceptOnForms(),
        ];
    }

    /**
     * The Select's options, keyed by backing value.
     *
     * @return array<string, string>
     */
    private static function ticketTypeOptions(): array
    {
        return array_column(TicketType::options(), 'label', 'value');
    }
}
