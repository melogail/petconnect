<?php

namespace App\Models;

use Database\Factories\SaveFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Support\Carbon;

/**
 * A user's bookmark of a saveable model.
 *
 * The `saves` table is polymorphic (saveable_type/saveable_id), matching likes,
 * comments, reviews and reports; there is no pet_id column.
 *
 * @property int $id
 * @property int $user_id
 * @property string $saveable_type
 * @property int $saveable_id
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable(['user_id', 'saveable_type', 'saveable_id'])]
class Save extends Model
{
    /** @use HasFactory<SaveFactory> */
    use HasFactory;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'saveable_id' => 'integer',
        ];
    }

    /**
     * @return MorphTo<Model, $this>
     */
    public function saveable(): MorphTo
    {
        return $this->morphTo();
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
