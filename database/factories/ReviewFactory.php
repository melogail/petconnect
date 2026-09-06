<?php

namespace Database\Factories;

use App\Models\Review;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Relations\Relation;

/**
 * @extends Factory<Review>
 */
class ReviewFactory extends Factory
{
    /**
     * The name of the factory's corresponding model.
     *
     * @var class-string<Review>
     */
    protected $model = Review::class;

    /**
     * Define the model's default state.
     *
     * Users are the only reviewable model on the Reviewable whitelist.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'rate' => fake()->numberBetween(1, 5),
            'comment' => fake()->optional(0.8)->paragraph(),
            'reviewable_type' => Relation::getMorphAlias(User::class),
            'reviewable_id' => User::factory(),
        ];
    }

    /**
     * Review the given user.
     */
    public function forUser(User $user): static
    {
        return $this->state(fn (array $attributes): array => [
            'reviewable_type' => Relation::getMorphAlias(User::class),
            'reviewable_id' => $user->getKey(),
        ]);
    }

    /**
     * Give the review an exact 1-5 rating.
     */
    public function rating(int $rate): static
    {
        return $this->state(fn (array $attributes): array => [
            'rate' => $rate,
        ]);
    }
}
