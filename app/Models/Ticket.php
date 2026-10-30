<?php

namespace App\Models;

use App\Enums\TicketType;
use Database\Factories\ContactFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * A message sent through the support page's contact form, read in the back
 * office.
 *
 * `name` and `email` are stored on every row, member or guest: a member's are
 * copied off the account by Actions\Contact\SubmitContactMessage, so the row
 * still reads correctly after the account changes its address or is deleted
 * (`user_id` is nulled on delete, nothing else cascades).
 *
 * @property int $id
 * @property int|null $user_id
 * @property string $name
 * @property string $email
 * @property TicketType $ticket_type
 * @property string $subject
 * @property string $message
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable(['user_id', 'name', 'email', 'ticket_type', 'subject', 'message'])]
class Contact extends Model
{
    /** @use HasFactory<ContactFactory> */
    use HasFactory;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'ticket_type' => TicketType::class,
        ];
    }

    /**
     * The member who sent the message, when it was not a guest.
     *
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
