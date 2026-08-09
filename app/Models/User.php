<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use App\Contracts\Likeable;
use App\Contracts\ReviewInterface;
use App\Notifications\VerifyEmailNotification;
use App\Observers\UserObserver;
use App\Traits\HasLikes;
use App\Traits\HasReviews;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Contracts\Translation\HasLocalePreference;
use Illuminate\Database\Eloquent\Attributes\ObservedBy;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Collection;
use Laravel\Fortify\TwoFactorAuthenticatable;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;

#[ObservedBy(UserObserver::class)]
class User extends Authenticatable implements HasLocalePreference, HasMedia, Likeable, MustVerifyEmail, ReviewInterface
{
    /** @use HasFactory<\Database\Factories\UserFactory> */
    use HasFactory, HasLikes, HasReviews, InteractsWithMedia, Notifiable, TwoFactorAuthenticatable;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $guarded = [];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'two_factor_secret',
        'two_factor_recovery_codes',
        'remember_token',
    ];

    /**
     * The attributes that should be appended to the model's array form.
     *
     * @var array
     */
    protected $appends = ['location'];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
        ];
    }

    /**
     * ============================
     * == CUSTOM METHODS ==
     * ============================
     */
    public function registerMediaCollections(): void
    {
        $this->addMediaCollection('users');
    }

    public function isVerified(): bool
    {
        return $this->email_verified_at ? true : false;
    }

    /**
     * Get the user's preferred locale for notifications and mail.
     */
    public function preferredLocale(): string
    {
        $locale = $this->locale ?: config('app.locale', 'en');
        $available = config('app.available_locales', ['en', 'ar']);

        return in_array($locale, $available, true)
            ? $locale
            : config('app.locale', 'en');
    }

    /**
     * Send the email verification notification.
     */
    public function sendEmailVerificationNotification(): void
    {
        $this->notify(new VerifyEmailNotification);
    }

    /**
     * ============================
     * == ACCESSORS AND MUTATORS ==
     * ============================
     */
    public function location(): Attribute
    {
        return Attribute::make(
            get: fn () => collect([
                $this->city,
                $this->state,
                $this->country,
            ])->filter()->implode(', ')
        );
    }

    /**
     * =======================
     * == RELATIONSHIPS
     * =======================
     */
    public function pets(): HasMany
    {
        return $this->hasMany(Pet::class);
    }

    public function givenReviews()
    {
        return $this->hasMany(Review::class, 'user_id');
    }

    public function conversations(): BelongsToMany
    {
        return $this->belongsToMany(Conversation::class, 'conversation_user', 'user_id', 'conversation_id')
            ->withPivot('last_read_at')
            ->withTimestamps();
    }

    /**
     * @return Collection<int, User>
     */
    public function likeNotificationRecipients(): Collection
    {
        return collect([$this]);
    }
}
