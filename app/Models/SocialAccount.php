<?php

namespace App\Models;

use App\Enums\SocialProvider;
use Database\Factories\SocialAccountFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * A third-party identity (Google, Facebook) linked to a user.
 *
 * Only the provider's stable user id is stored: no access or refresh tokens,
 * because the application never calls the provider on the user's behalf after
 * sign-in. The email is not stored either — the user's own `email` column is
 * the one that matters, and the provider's copy at sign-in time is what
 * Actions\Auth\ResolveSocialUser used to find or create that row.
 *
 * `user_id` is deliberately absent from #[Fillable]: a row is only ever created
 * through `$user->socialAccounts()->create()`, so the owner comes from the
 * relation and never from a request bag.
 *
 * @property int $id
 * @property int $user_id
 * @property SocialProvider $provider
 * @property string $provider_id
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable(['provider', 'provider_id'])]
class SocialAccount extends Model
{
    /** @use HasFactory<SocialAccountFactory> */
    use HasFactory;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'provider' => SocialProvider::class,
        ];
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
