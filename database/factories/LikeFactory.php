<?php

namespace Database\Factories;

use App\Models\Comment;
use App\Models\Pet;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Like>
 */
class LikeFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $likeableType = fake()->randomElement([Pet::class, Comment::class]);

        return [
            'user_id' => User::factory(),
            'likeable_type' => $likeableType,
            'likeable_id' => $likeableType::factory(),
        ];
    }

    /**
     * Indicate that the like is for a pet.
     */
    public function forPet($petId = null): static
    {
        return $this->state(fn (array $attributes) => [
            'likeable_type' => Pet::class,
            'likeable_id' => $petId ?? Pet::factory(),
        ]);
    }

    /**
     * Indicate that the like is for a comment.
     */
    public function forComment($commentId = null): static
    {
        return $this->state(fn (array $attributes) => [
            'likeable_type' => Comment::class,
            'likeable_id' => $commentId ?? Comment::factory(),
        ]);
    }

    /**
     * Indicate that the like is for a user profile.
     */
    public function forUser($userId = null): static
    {
        return $this->state(fn (array $attributes) => [
            'likeable_type' => User::class,
            'likeable_id' => $userId ?? User::factory(),
        ]);
    }
}
