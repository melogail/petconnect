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
 * `status`, `pinned_by`, `pinned_at` and `edited_at` are deliberately not mass
 * assignable: status is a delivery state the application owns (the column
 * defaults to `sent`), pinning is privileged, and "this text was revised" is
 * the application's record rather than the client's claim, so the pin/unpin and
 * edit flows set those columns explicitly rather than accepting them from a
 * request payload.
 *
 * `edited_at` exists so that "was this message edited?" has a column of its
 * own. It used to be inferred from `updated_at` moving past `created_at`, which
 * made every unrelated write to the row — pinning, a future delivery-state
 * transition, a restore — look like an edit of somebody's words. Only
 * Pipelines\Messages\Revise\PersistContent writes it.
 *
 * @property int $id
 * @property int $conversation_id
 * @property int $sender_id
 * @property string $content
 * @property MessageType $type
 * @property MessageStatus $status
 * @property int|null $pinned_by
 * @property Carbon|null $pinned_at
 * @property Carbon|null $edited_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property Carbon|null $deleted_at
 * @property-read bool $is_pinned
 * @property-read bool $is_edited
 */
#[Appends(['is_pinned', 'is_edited'])]
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
            'edited_at' => 'datetime',
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
     * Whether the sender has revised the text since sending it.
     *
     * Read from `edited_at` and nothing else. `updated_at` is the row's
     * last-write stamp and moves for reasons that are not edits — pinning,
     * unpinning, a restore, any delivery-state column a later phase adds — so
     * deriving this from it published "edited" for messages nobody had touched
     * the words of.
     *
     * @return Attribute<bool, never>
     */
    protected function isEdited(): Attribute
    {
        return Attribute::make(
            get: fn (): bool => $this->edited_at !== null,
        );
    }

    /**
     * Resolve a route-bound message only while the conversation it lives in is
     * still visible.
     *
     * A message is a child model addressed by a sequential id — `messages.update`,
     * `messages.destroy`, `messages.pin` — and none of those URLs names the
     * conversation. Left to the default binding, a soft-deleted conversation's
     * messages stayed writable at a guessable id while `conversations.show` for
     * the same thread 404'd. It is the shape that leaked a retired listing's
     * whole discussion in the comments vertical, so it is fixed the same way and
     * in the same place: on the child, once, rather than per route.
     *
     * `conversation` is a BelongsTo, so unlike Comment's MorphTo a `whereHas`
     * constraint would have been possible here — the relation is loaded instead
     * for two reasons. It keeps the two overrides identical to read, and every
     * caller of a `{message}` route needs the conversation anyway (MessagePolicy
     * asks it about participation, MessageController redirects to it), so the
     * query is one the request was going to issue regardless.
     *
     * Returning null makes Illuminate\Routing\ImplicitRouteBinding raise
     * ModelNotFoundException, i.e. the same 404 `conversations.show` gives.
     *
     * @param  mixed  $value
     * @param  string|null  $field
     */
    public function resolveRouteBinding($value, $field = null): ?self
    {
        /** @var self|null $message */
        $message = parent::resolveRouteBinding($value, $field);

        if ($message === null) {
            return null;
        }

        $message->loadMissing('conversation');

        return $message->conversation === null ? null : $message;
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
