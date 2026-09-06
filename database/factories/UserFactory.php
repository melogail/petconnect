<?php

namespace Database\Factories;

use App\Models\User;
use Database\Seeders\Concerns\ReadsSeedData;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use JsonException;
use PragmaRX\Google2FA\Google2FA;

/**
 * @extends Factory<User>
 */
class UserFactory extends Factory
{
    use ReadsSeedData;

    /**
     * The current password being used by the factory.
     */
    protected static ?string $password;

    /**
     * Define the model's default state.
     *
     * `media_directory_name` is deliberately absent: App\Observers\UserObserver
     * assigns it on creating, and the column is unique.
     *
     * The location, the timezone and the street line all come from the same
     * real cities the seeders use, so a bare User::factory() never lands in a
     * country the app has no data for.
     *
     * @return array<string, mixed>
     *
     * @throws JsonException
     */
    public function definition(): array
    {
        $location = fake()->randomElement($this->locations());

        return [
            'name' => fake()->name(),
            'username' => $this->uniqueHandle(),
            'bio' => fake()->optional(0.7)->paragraph(),
            'email' => fake()->unique()->safeEmail(),
            'email_verified_at' => now(),
            'password' => static::$password ??= Hash::make('password'),
            'phone' => fake()->numerify('+20-1##-###-####'),
            'country' => $location['country'],
            'state' => $location['state'],
            'city' => $location['city'],
            'address' => $this->streetAddress(),
            'lat' => $this->jitter($location['latitude']),
            'lng' => $this->jitter($location['longitude']),
            'timezone' => $location['timezone'],
            'locale' => 'en',
            'is_active' => true,
            'last_seen_at' => fake()->dateTimeBetween('-7 days', 'now'),
            'remember_token' => Str::random(10),
            'two_factor_secret' => null,
            'two_factor_recovery_codes' => null,
            'two_factor_confirmed_at' => null,
        ];
    }

    /**
     * A public handle that satisfies the application's own rule for one.
     *
     * `App\Concerns\ProfileValidationRules::usernameRules()` is
     * `alpha_dash|min:3|max:50`, and Faker's `userName()` joins its parts with a
     * **dot** — `wilbert.hartmann` — which `alpha_dash` rejects. Every factory
     * member therefore carried a handle its own profile form would 422, which
     * went unnoticed while nothing revalidated an existing row and started
     * mattering the moment App\Nova\User began enforcing the same rules.
     *
     * Swapping `.` for `_` keeps Faker's uniqueness guarantee intact rather than
     * needing the random suffix CategoryFactory's slugs use: `_` never appears
     * in Faker's own output for this generator, so the mapping is injective and
     * two distinct draws cannot collide on the unique index.
     */
    protected function uniqueHandle(): string
    {
        return str_replace('.', '_', fake()->unique()->userName());
    }

    /**
     * Place the member in one of the cities from database/data/locations.json,
     * scattered a few kilometres around the centre so members in one city do
     * not stack on a single point.
     *
     * @param  array{city: string, state: string, country: string, postal_code: string, latitude: float, longitude: float, timezone: string}  $location
     */
    public function inCity(array $location): static
    {
        return $this->state(fn (array $attributes): array => [
            'city' => $location['city'],
            'state' => $location['state'],
            'country' => $location['country'],
            'lat' => $this->jitter($location['latitude']),
            'lng' => $this->jitter($location['longitude']),
            'timezone' => $location['timezone'],
        ]);
    }

    /**
     * Indicate that the model's email address should be unverified.
     */
    public function unverified(): static
    {
        return $this->state(fn (array $attributes): array => [
            'email_verified_at' => null,
        ]);
    }

    /**
     * Indicate that the model has two-factor authentication configured.
     *
     * The secret is a real base32 key rather than a placeholder. It used to be
     * the literal `'secret'`, which is 6 characters and not valid base32, so any
     * request that actually verified a code against it — a POST to
     * `two-factor.login` — died inside Google2FA with
     * `SecretKeyTooShortException` and surfaced as a 500 rather than as an
     * invalid code. Nothing noticed while the challenge was only ever rendered.
     * Same point as the `uniqueHandle()` note in .ai/rules/factories.md: a
     * fixture the application's own code cannot accept is a latent bug.
     *
     * Pass `$secret` when the test needs to compute the current code from it;
     * pass `$recoveryCodes` when it needs to spend one and see the rest survive.
     *
     * @param  list<string>|null  $recoveryCodes
     */
    public function withTwoFactor(?string $secret = null, ?array $recoveryCodes = null): static
    {
        return $this->state(fn (array $attributes): array => [
            'two_factor_secret' => encrypt($secret ?? app(Google2FA::class)->generateSecretKey()),
            'two_factor_recovery_codes' => encrypt(json_encode($recoveryCodes ?? ['recovery-code-1'])),
            'two_factor_confirmed_at' => now(),
        ]);
    }

    /**
     * Indicate that the model does not have two-factor authentication configured.
     */
    public function withoutTwoFactor(): static
    {
        return $this->state(fn (array $attributes): array => [
            'two_factor_secret' => null,
            'two_factor_recovery_codes' => null,
            'two_factor_confirmed_at' => null,
        ]);
    }

    /**
     * Indicate that the account has been deactivated.
     */
    public function inactive(): static
    {
        return $this->state(fn (array $attributes): array => [
            'is_active' => false,
        ]);
    }
}
