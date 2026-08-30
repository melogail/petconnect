<?php

namespace App\Models;

use App\Concerns\HasLikes;
use App\Concerns\HasReviews;
use App\Contracts\Likeable;
use App\Observers\UserObserver;
use Database\Factories\UserFactory;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Contracts\Translation\HasLocalePreference;
use Illuminate\Database\Eloquent\Attributes\Appends;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Attributes\ObservedBy;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Laravel\Fortify\Contracts\PasskeyUser;
use Laravel\Fortify\PasskeyAuthenticatable;
use Laravel\Fortify\TwoFactorAuthenticatable;
use Spatie\Image\Enums\Fit;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

/**
 * @property int $id
 * @property string $name
 * @property string|null $username
 * @property string|null $media_directory_name
 * @property string|null $bio
 * @property string $email
 * @property Carbon|null $email_verified_at
 * @property string $password
 * @property string|null $phone
 * @property string|null $country
 * @property string|null $state
 * @property string|null $city
 * @property string|null $address
 * @property string|null $lat
 * @property string|null $lng
 * @property string|null $timezone
 * @property string $locale
 * @property bool $is_active
 * @property Carbon|null $last_seen_at
 * @property string|null $two_factor_secret
 * @property string|null $two_factor_recovery_codes
 * @property Carbon|null $two_factor_confirmed_at
 * @property string|null $remember_token
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read string $location
 */
#[Appends(['location'])]
#[Fillable([
    'name',
    'username',
    'bio',
    'email',
    'password',
    'phone',
    'country',
    'state',
    'city',
    'address',
    'lat',
    'lng',
    'timezone',
    'locale',
])]
#[Hidden(['password', 'two_factor_secret', 'two_factor_recovery_codes', 'remember_token'])]
#[ObservedBy([UserObserver::class])]
class User extends Authenticatable implements HasLocalePreference, HasMedia, Likeable, MustVerifyEmail, PasskeyUser
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, HasLikes, HasReviews, InteractsWithMedia, Notifiable, PasskeyAuthenticatable, TwoFactorAuthenticatable;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'last_seen_at' => 'datetime',
            'is_active' => 'boolean',
            'password' => 'hashed',
            'two_factor_confirmed_at' => 'datetime',
        ];
    }

    /**
     * In practice a single avatar; the collection is not marked singleFile so a
     * user can keep previous avatars if the product ever calls for it.
     */
    public function registerMediaCollections(): void
    {
        $this->addMediaCollection('users')
            ->useDisk(config('media-library.disk_name'));
    }

    /**
     * Avatars are only ever rendered small, so both derivatives are square.
     */
    public function registerMediaConversions(?Media $media = null): void
    {
        $this->addMediaConversion('thumb')
            ->fit(Fit::Crop, 96, 96)
            ->nonQueued();

        $this->addMediaConversion('display')
            ->fit(Fit::Crop, 512, 512);
    }

    /**
     * Whether the user has confirmed their email address.
     */
    public function isVerified(): bool
    {
        return $this->email_verified_at !== null;
    }

    /**
     * Get the user's preferred locale for notifications and mail.
     */
    public function preferredLocale(): string
    {
        $fallback = (string) config('app.locale', 'en');
        $locale = (string) ($this->locale ?: $fallback);

        /** @var array<int, string> $available */
        $available = config('app.available_locales', ['en', 'ar']);

        return in_array($locale, $available, true) ? $locale : $fallback;
    }

    /**
     * The user's human readable location, e.g. "Cairo, Cairo, Egypt".
     *
     * @return Attribute<string, never>
     */
    protected function location(): Attribute
    {
        return Attribute::make(
            get: fn (): string => collect([$this->city, $this->state, $this->country])
                ->filter()
                ->implode(', '),
        );
    }

    /**
     * @return HasMany<Pet, $this>
     */
    public function pets(): HasMany
    {
        return $this->hasMany(Pet::class);
    }

    /**
     * Reviews this user has written about others; reviews() holds the ones
     * written about this user.
     *
     * @return HasMany<Review, $this>
     */
    public function givenReviews(): HasMany
    {
        return $this->hasMany(Review::class, 'user_id');
    }

    /**
     * @return HasMany<Comment, $this>
     */
    public function comments(): HasMany
    {
        return $this->hasMany(Comment::class);
    }

    /**
     * @return HasMany<Report, $this>
     */
    public function reports(): HasMany
    {
        return $this->hasMany(Report::class);
    }

    /**
     * The models this user has bookmarked.
     *
     * @return HasMany<Save, $this>
     */
    public function saves(): HasMany
    {
        return $this->hasMany(Save::class);
    }

    /**
     * @return BelongsToMany<Conversation, $this>
     */
    public function conversations(): BelongsToMany
    {
        return $this->belongsToMany(Conversation::class, 'conversation_user', 'user_id', 'conversation_id')
            ->withPivot('last_read_at')
            ->withTimestamps();
    }

    /**
     * A liked user is notified about their own like.
     *
     * @return Collection<int, User>
     */
    public function likeNotificationRecipients(): Collection
    {
        return collect([$this]);
    }
}
