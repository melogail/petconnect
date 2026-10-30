<?php

namespace App\Nova;

use App\Concerns\TicketValidationRules;
use App\Models\ContactMessage as ContactMessageModel;
use App\Nova\Policies\ContactMessagePolicy;
use Laravel\Nova\Fields\DateTime;
use Laravel\Nova\Fields\Field;
use Laravel\Nova\Fields\ID;
use Laravel\Nova\Fields\Text;
use Laravel\Nova\Fields\Textarea;
use Laravel\Nova\Http\Requests\NovaRequest;

/**
 * The guest inbox: one-off messages from visitors without an account.
 *
 * Every field is read only. A message is what somebody wrote, so there is no
 * create form and no update form (ContactMessagePolicy); the admin reads it,
 * replies from their own mail client to the address on the row — there is no
 * thread to answer into, unlike a Ticket — and deletes it when it is dealt
 * with. Nothing references the row, so Nova's ordinary delete is the right
 * tool and no DestructiveAction stands in for it.
 *
 * @extends \App\Nova\Resource<ContactMessageModel>
 */
class ContactMessage extends Resource
{
    use TicketValidationRules;

    /**
     * The model the resource corresponds to.
     *
     * @var class-string<ContactMessageModel>
     */
    public static $model  = ContactMessageModel::class;

    /**
     * The Nova-only policy the resource corresponds to.
     *
     * @var class-string<ContactMessagePolicy>
     */
    public static $policy = ContactMessagePolicy::class;

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
     * Get the fields displayed by the resource.
     *
     * @return array<int, Field>
     */
    public function fields(NovaRequest $request): array
    {
        return [
            ID::make()->sortable(),

            Text::make('Name')
                ->rules($this->senderNameRules())
                ->readonly()
                ->sortable(),

            Text::make('Email')
                ->rules($this->senderEmailRules())
                ->readonly()
                ->copyable(),

            Text::make('Subject')
                ->rules($this->subjectRules())
                ->readonly()
                ->sortable(),

            Textarea::make('Message')
                ->rules($this->messageBodyRules())
                ->readonly()
                ->alwaysShow(),

            DateTime::make('Sent', 'created_at')
                ->readonly()
                ->sortable()
                ->exceptOnForms(),
        ];
    }
}
