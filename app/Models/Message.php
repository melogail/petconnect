<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class Message extends Model
{
    /** @use HasFactory<\Database\Factories\MessageFactory> */
    use HasFactory, SoftDeletes;

    public const TYPE_TEXT = 'text';

    public const STATUS_SENT = 'sent';

    protected $guarded = [];

    protected $appends = ['is_pinned'];

    /**
     * Per-user read position lives on conversation_user.last_read_at.
     * The messages.read_at column is reserved for optional per-message receipts (not used by default flows).
     */
    protected function casts(): array
    {
        return [
            'read_at' => 'datetime',
            'pinned_at' => 'datetime',
        ];
    }

    /**
     * =======================
     * == ACCESSORS AND MUTATORS
     * =======================
     */
    public function isPinned(): Attribute
    {
        return Attribute::make(get: fn () => $this->pinned_at !== null);
    }

    /**
     * =======================
     * == RELATIONSHIPS
     * =======================
     */
    public function conversation(): BelongsTo
    {
        return $this->belongsTo(Conversation::class);
    }

    public function sender(): BelongsTo
    {
        return $this->belongsTo(User::class, 'sender_id');
    }

    public function pinnedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'pinned_by');
    }
}
