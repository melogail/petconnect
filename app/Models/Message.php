<?php

namespace App\Models;

use App\Enums\MessageStatus;
use App\Enums\MessageType;
use App\Observers\MessageObserver;
use Database\Factories\MessageFactory;
use Illuminate\Database\Eloquent\Attributes\Appends;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\ObservedBy;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;

/**
 * A single message inside a conversation.
 *
 * Per-user read position lives on conversation_user.last_read_at, so there is
 * no per-message read receipt column.
 *
 * `status`, `pinned_by` and `pinned_at` are deliberately not mass assignable:
 * status is a delivery state the application owns (the column defaults to
 * `sent`) and pinning is privileged, so the pin/unpin Action sets both columns
 * explicitly rather than accepting them from a request payload.
 *
 * @property int $id
 * @property int $conversation_id
 * @property int $sender_id
 * @property string $content
 * @property MessageType $type
 * @property MessageStatus $status
 * @property int|null $pinned_by
 * @property Carbon|null $pinned_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property Carbon|null $deleted_at
 * @property-read bool $is_pinned
 */
#[Appends(['is_pinned'])]
#[Fillable(['conversation_id', 'sender_id', 'content', 'type'])]
#[ObservedBy([MessageObserver::class])]
class Message extends Model
{
    /** @use HasFactory<MessageFactory> */
    use HasFactory, SoftDeletes;

    /**
     * The model's default attribute values.
     *
     * `status` is out of #[Fillable] and the DB default only lands in the row,
     * never on the instance, so `Message::create([...])->status` would be null
     * in memory while `@property MessageStatus $status` promises an enum — and
     * an API Resource or Inertia prop built from that model would ship the lie.
     * The raw backing value is stored here; the cast resolves it on access.
     *
     * @var array{status: string}
     */
    protected $attributes = [
        'status' => MessageStatus::Sent->value,
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'type' => MessageType::class,
            'status' => MessageStatus::class,
            'pinned_at' => 'datetime',
        ];
    }

    /**
     * @return Attribute<bool, never>
     */
    protected function isPinned(): Attribute
    {
        return Attribute::make(
            get: fn (): bool => $this->pinned_at !== null,
        );
    }

    /**
     * @return BelongsTo<Conversation, $this>
     */
    public function conversation(): BelongsTo
    {
        return $this->belongsTo(Conversation::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function sender(): BelongsTo
    {
        return $this->belongsTo(User::class, 'sender_id');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function pinnedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'pinned_by');
    }
}
