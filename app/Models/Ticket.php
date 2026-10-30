<?php

namespace App\Models;

use App\Enums\TicketCategory;
use App\Enums\TicketStatus;
use Carbon\CarbonImmutable;
use Database\Factories\TicketFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * A support ticket: the header of a conversation between a sender and the
 * team. What was said is in `messages()`, oldest first; the first one is what
 * the sender opened the ticket with.
 *
 * Tickets are members-only: a guest's message is a ContactMessage, a one-off
 * row with no thread. `name` and `email` are still copied off the account by
 * Actions\Tickets\OpenTicket, so the row reads correctly after the account
 * changes its address or is deleted (`user_id` is nulled on delete, nothing
 * else cascades) and the team's replies still have an address to go to.
 *
 * `status`, `last_activity_at` and `closed_at` are outside #[Fillable]: they
 * are written by the ticket flows (OpenTicket, ReplyToTicket, CloseTicket and
 * Nova's ChangeTicketStatus), never off a request bag.
 *
 * @property int $id
 * @property int|null $user_id
 * @property string $name
 * @property string $email
 * @property TicketCategory $category
 * @property string $subject
 * @property TicketStatus $status
 * @property CarbonImmutable|null $last_activity_at Written from now(), which is CarbonImmutable under Date::use().
 * @property CarbonImmutable|null $closed_at Same writer shape as $last_activity_at.
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable(['user_id', 'name', 'email', 'category', 'subject'])]
class Ticket extends Model
{
    /** @use HasFactory<TicketFactory> */
    use HasFactory;

    /**
     * The model's default attribute values.
     *
     * `status` is out of #[Fillable] and the DB default only lands in the row,
     * never on the instance, so without this `Ticket::create([...])->status`
     * would read null in memory (.ai/rules/models.md).
     *
     * @var array{status: string}
     */
    protected $attributes = [
        'status' => TicketStatus::Open->value,
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'category'         => TicketCategory::class,
            'status'           => TicketStatus::class,
            'last_activity_at' => 'datetime',
            'closed_at'        => 'datetime',
        ];
    }

    /**
     * The member who opened the ticket; null once their account is deleted.
     *
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * The thread, oldest first.
     *
     * @return HasMany<TicketMessage, $this>
     */
    public function messages(): HasMany
    {
        return $this->hasMany(TicketMessage::class)->oldest();
    }

    /**
     * Tickets waiting on the team.
     *
     * @param  Builder<Ticket>  $query
     */
    #[Scope]
    protected function open(Builder $query): void
    {
        $query->where('status', TicketStatus::Open);
    }

    public function isClosed(): bool
    {
        return $this->status === TicketStatus::Closed;
    }
}
