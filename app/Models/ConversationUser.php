<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\Pivot;
use Illuminate\Support\Carbon;

/**
 * The conversation_user pivot, carrying each participant's read cursor.
 *
 * It exists so `last_read_at` is a Carbon everywhere instead of a raw pivot
 * string. The table has an auto-incrementing `id`, unlike a default pivot.
 *
 * @property int $id
 * @property int $conversation_id
 * @property int $user_id
 * @property Carbon|null $last_read_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
class ConversationUser extends Pivot
{
    protected $table = 'conversation_user';

    public $incrementing = true;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'last_read_at' => 'datetime',
        ];
    }
}
