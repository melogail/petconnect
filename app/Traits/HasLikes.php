<?php

namespace App\Traits;

use App\Models\Like;
use App\Models\User;
use Illuminate\Database\Eloquent\Relations\MorphMany;

trait HasLikes
{
    public function likes(): MorphMany
    {
        return $this->morphMany(Like::class, 'likeable');
    }

    public function makeLike(bool $toBoolean = false): Like|bool
    {
        $result = $this->likes()->firstOrCreate([
            'user_id' => auth()->id(),
        ]);

        return $toBoolean ? (bool) $result : $result;

    }

    public function removeLike(): bool
    {
        return (bool) $this->likes()
            ->where('user_id', auth()->id())
            ->delete();
    }

    public function isLikedBy(User $user): bool
    {
        return $this->likes()->where('user_id', $user->id)->exists();
    }
}
