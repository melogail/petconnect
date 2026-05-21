<?php

namespace Database\Factories;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Review>
 */
class ReviewFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'rate' => fake()->numberBetween(1, 5),
            'comment' => fake()->optional(0.8)->paragraph(),
            'reviewable_type' => User::class,
            'reviewable_id' => User::factory(),
        ];
    }

    /**
     * Indicate that the review is for a specific user.
     */
    public function forUser($userId): static
    {
        return $this->state(fn (array $attributes) => [
            'reviewable_type' => User::class,
            'reviewable_id' => $userId,
        ]);
    }

    /**
     * Set a specific rating.
     */
    public function rating($rating): static
    {
        return $this->state(fn (array $attributes) => [
            'rate' => $rating,
        ]);
    }
}
