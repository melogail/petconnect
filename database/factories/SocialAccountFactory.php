<?php

namespace Database\Factories;

use App\Enums\SocialProvider;
use App\Models\SocialAccount;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * Linked identities are unique per (provider, provider_id); the id is drawn from
 * Faker's unique pool so two rows in one test cannot collide.
 *
 * @extends Factory<SocialAccount>
 */
class SocialAccountFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id'     => User::factory(),
            'provider'    => fake()->randomElement(SocialProvider::cases()),
            'provider_id' => (string) fake()->unique()->numerify('####################'),
        ];
    }

    /**
     * Pin the provider this identity belongs to.
     */
    public function provider(SocialProvider $provider): static
    {
        return $this->state(fn (array $attributes): array => [
            'provider' => $provider,
        ]);
    }
}
