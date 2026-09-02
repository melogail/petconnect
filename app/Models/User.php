<?php

namespace App\Models;

use App\Concerns\HasLikes;
use App\Concerns\HasReviews;
use App\Contracts\Likeable;
use App\Contracts\Reviewable;
use App\MediaLibrary\ConvertibleImageTypes;
use App\Notifications\VerifyEmailNotification;
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
 * @property string|float|null $lat Uncast decimal(10, 8): a string on MySQL, a float on SQLite.
 * @property string|float|null $lng Uncast decimal(11, 8): see $lat, and ProfileFormResource.
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
class User extends Authenticatable implements HasLocalePreference, HasMedia, Likeable, MustVerifyEmail, PasskeyUser, Reviewable
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
     * Bind `{user}` by **id**, never by `username`.
     *
     * Stated explicitly and redundantly — `id` is already the default — because
     * this is the one file somebody would change it in, and changing it breaks
     * far more than the profile route they would be looking at.
     * App\Concerns\ResolvesMorphTarget::findVisibleOrFail() resolves every
     * Reviewable and Reportable target through `getRouteKeyName()`, so a User
     * keyed on a string column would have all of those lookups compare an
     * integer morph id against `users.username`. On SQLite that matches nothing
     * and returns a 404; on MySQL it is a silent type-juggled comparison. The
     * profile route pins the other half with `whereNumber('user')`.
     *
     * A `/@handle` URL is a routing change, not a model change: give it its own
     * route with its own explicit `->where(...)` binding and leave this alone.
     * The same reasoning is recorded in ProfileValidationRules::usernameRules(),
     * routes/web.php and Web\ProfileController::show.
     */
    public function getRouteKeyName(): string
    {
        return 'id';
    }

    /**
     * Refuse to bind a deactivated account, anywhere an id names one.
     *
     * ## This reverses an earlier decision, and here is why
     *
     * UserPolicy::view has refused a deactivated profile since Phase 2e, and
     * that policy's docblock recorded a deliberate choice *not* to put the same
     * check here — the reasoning being .ai/rules/app.md's line that "hidden by
     * a global scope goes in resolveRouteBinding, hidden by state goes in a
     * policy", plus a worry that a binding override would "silently change
     * which users can be reviewed".
     *
     * That worry was the finding. Measured on one deactivated account:
     *
     *     GET  /profile/{id}        403     POST /profile/{id}/like   403
     *     GET  /reviews/user/{id}   200     POST /reviews/user/{id}   302
     *
     * The reviews vertical does not route-model-bind a user; it resolves one
     * through `App\Enums\Reviewable::findVisibleOrFail()`, which delegates to
     * this method precisely so a model can record what "may this id be
     * addressed right now" means. With no override the delegation fell through
     * to Eloquent's default and answered "yes" — so a deactivated account's
     * review list stayed public (author name, username and location with it)
     * and, worse, anyone could still *write* a review about them and deliver
     * the notification to an account the system had decided was not
     * addressable.
     *
     * The rule in .ai/rules/app.md draws the line at whether the check is one a
     * binding override can answer completely, and this one is: `is_active` is a
     * column on the row the binding just fetched. It is not the participation
     * question that made `Message` need a policy as well. So it belongs here,
     * where `reviews.index`, `reviews.store`, a future `Reportable::User` and
     * anything else that resolves a user by bare id inherit it by construction
     * rather than each remembering.
     *
     * ## The consequence to know about: profile pages are now 404, not 403
     *
     * `profile.show` and `profile.like` bind `{user}` through this method, so a
     * deactivated profile is a ModelNotFoundException before the controller
     * runs and never reaches `$this->authorize('view', $user)`. That is the
     * same answer `Comment::resolveRouteBinding()` gives for a comment on a
     * hidden listing, and it is the stronger one — a 403 confirms the account
     * exists and a 404 does not.
     *
     * UserPolicy::view keeps its `isActive()` check regardless. It is no longer
     * the thing that produces the status code on those two routes, but it is
     * still the answer to `Gate::allows('view', $deactivated)` asked directly,
     * and any future page that reaches a User some other way (a relation, a
     * collection) gets it.
     *
     * Nova is unaffected: it resolves resources with its own keyed queries and
     * never calls `resolveRouteBinding()`, so an admin can still open and
     * reactivate a deactivated account.
     */
    public function resolveRouteBinding($value, $field = null): ?self
    {
        /** @var self|null $user */
        $user = parent::resolveRouteBinding($value, $field);

        if ($user === null) {
            return null;
        }

        return $user->isActive() ? $user : null;
    }

    /**
     * In practice a single avatar; the collection is not marked singleFile so a
     * user can keep previous avatars if the product ever calls for it.
     *
     * `acceptsMimeTypes()` restates the validator's format list at the
     * collection as defence in depth for any path that never went through a
     * Form Request. Both are built from App\MediaLibrary\ConvertibleImageTypes,
     * so they cannot disagree.
     */
    public function registerMediaCollections(): void
    {
        $this->addMediaCollection('users')
            ->acceptsMimeTypes(ConvertibleImageTypes::MIME_TYPES)
            ->useDisk(config('media-library.disk_name'));
    }

    /**
     * Avatars are only ever rendered small, so both derivatives are square.
     *
     * ## Both are nonQueued, and that is a decision, not an oversight
     *
     * `display` used to be queued (the package default,
     * `media-library.queue_conversions_by_default`). `QUEUE_CONNECTION` is
     * `database` and this application ships no worker — no `queue:work` in
     * composer.json, no supervisor config, no Horizon — so the job was written
     * to the `jobs` table and never run. Measured on a fresh upload:
     * `generated_conversions` held `{"thumb":true}` and one job sat pending.
     *
     * Nothing looked broken, because `getFirstMediaUrl()` falls back to the
     * original when the named conversion has not been generated. Profile pages
     * were therefore serving the raw upload — 23 KB in the fixture, but
     * `petconnect.profiles.max_avatar_kilobytes` allows 2 MB — where a ~40 KB
     * 512px crop was intended, and `PetMediaResource` calls
     * `Media::getUrl('display')`, which does NOT fall back and would resolve to
     * a file that was never written.
     *
     * The three ways out were: run a worker, read `thumb` in the profile
     * resources, or generate `display` inline. A worker is infrastructure this
     * project does not have and cannot assume; `thumb` is 96px and the profile
     * header renders larger than that. So it is inline. The upload request
     * already pays for one nonQueued crop of the same source image, and an
     * avatar is capped at 2 MB, so the second crop is a bounded cost on a rare
     * request rather than a dependency on a process that does not exist.
     *
     * If a worker is ever deployed, this is the line to revisit — and `Pet`,
     * `Category` and `Breed` still queue their `display` conversions, so they
     * have the same silent fallback today.
     */
    public function registerMediaConversions(?Media $media = null): void
    {
        $this->addMediaConversion('thumb')
            ->fit(Fit::Crop, 96, 96)
            ->nonQueued();

        $this->addMediaConversion('display')
            ->fit(Fit::Crop, 512, 512)
            ->nonQueued();
    }

    /**
     * Whether the user has confirmed their email address.
     */
    public function isVerified(): bool
    {
        return $this->email_verified_at !== null;
    }

    /**
     * Whether this account may be used at all.
     *
     * The single definition of "deactivated" in the application. Everything
     * that acts on the flag asks this method rather than reading `is_active`:
     * Http\Middleware\EnsureAccountIsActive (which ends the session on the
     * next request, whatever established it — password, passkey or an existing
     * cookie), UserPolicy::view (which refuses the public profile) and
     * acceptsMessagesFrom() below (which refuses delivery). Read that
     * middleware's docblock for what the three of them add up to.
     *
     * `is_active` is deliberately absent from #[Fillable], so no request bag
     * can flip it — the profile form cannot deactivate an account and neither
     * can a forged field. It is a moderation decision, set in Nova on the
     * `admins` guard, and self-service deactivation would be its own flow with
     * its own confirmation.
     */
    public function isActive(): bool
    {
        return $this->is_active;
    }

    /**
     * A fresh opaque directory name for this user's uploaded files.
     *
     * The one place the value is drawn. UserObserver::creating is the one place
     * it is *assigned* — factories, seeders, Nova and the registration flow all
     * arrive there — and Actions\Users\RegisterUser retries a create() when the
     * unique index refuses a draw, which re-runs the observer and therefore
     * redraws through this method.
     *
     * 10^15 to 10^18 inclusive, so 16 to 19 digits. Uniqueness is the DB unique
     * index's guarantee, not this method's: it draws at random and checks
     * nothing.
     */
    public static function freshMediaDirectoryName(): string
    {
        return (string) random_int(10 ** 15, 10 ** 18);
    }

    /**
     * Whether this user will accept a message written by the given sender.
     *
     * The single definition of recipient-side consent in the application. Both
     * write paths ask it — Pipelines\Messages\Send\EnsureRecipientAccepts for
     * every message, and Pipelines\Messages\StartDirectConversation\
     * EnsureRecipientAccepts for a thread opened with no message at all — via
     * Conversation::acceptsMessagesFrom(), so a rule added here takes effect on
     * both without either flow being edited.
     *
     * Today it is `is_active`: a deactivated account receives nothing. A
     * recipient-side block list and per-recipient message settings are their
     * own vertical (a table, a UI, a policy of their own) and land as further
     * checks in this method — including the initiator-side half, "has this user
     * blocked the sender", which is why the sender is a parameter rather than
     * this being an `acceptsMessages()` predicate about one account.
     *
     * `is_active` used to gate *only* this, so a deactivated account could
     * still sign in, publish, comment and like. It no longer does: deactivation
     * now also ends the session, refuses the sign-in and hides the public
     * profile. See isActive() and Http\Middleware\EnsureAccountIsActive.
     */
    public function acceptsMessagesFrom(User $sender): bool
    {
        return $this->isActive();
    }

    /**
     * Get the user's preferred locale for notifications and mail.
     */
    public function preferredLocale(): string
    {
        $fallback = (string) config('app.locale', 'en');
        $locale = (string) ($this->locale ?: $fallback);

        /** @var list<string> $supported */
        $supported = config('petconnect.locales.supported', ['en']);

        return in_array($locale, $supported, true) ? $locale : $fallback;
    }

    /**
     * Send the branded, locale-aware verification email instead of Laravel's
     * plain one.
     *
     * Overriding the method rather than swapping the notification in a listener
     * keeps every sender — Fortify's registration controller, the
     * `verification.send` route, a console command — on the same mail without
     * any of them knowing about it. See App\Notifications\VerifyEmailNotification.
     */
    public function sendEmailVerificationNotification(): void
    {
        $this->notify(new VerifyEmailNotification);
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
     * The inverse of Conversation::users(); both sides must name the same pivot
     * model, or last_read_at is a Carbon on one relation and a string on the other.
     *
     * @return BelongsToMany<Conversation, $this, ConversationUser>
     */
    public function conversations(): BelongsToMany
    {
        return $this->belongsToMany(Conversation::class, 'conversation_user', 'user_id', 'conversation_id')
            ->using(ConversationUser::class)
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

    /**
     * A review of a user is about that user: they are told about it, and they
     * are the one person who may not write it.
     *
     * Both halves come from this one method on purpose — see
     * App\Contracts\Reviewable. It costs no query, because the subject is the
     * model already in hand.
     *
     * @return Collection<int, User>
     */
    public function reviewSubjects(): Collection
    {
        return collect([$this]);
    }
}
