<?php

namespace Database\Factories;

use App\Models\Comment;
use App\Models\Like;
use App\Models\Pet;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Relations\Relation;

/**
 * Likes are unique per (user, likeable); build them from distinct pairs, or go
 * through Pet::like()/toggleLike(), which use firstOrCreate.
 *
 * @extends Factory<Like>
 */
class LikeFactory extends Factory
{
    /**
     * The name of the factory's corresponding model.
     *
     * @var class-string<Like>
     */
    protected $model = Like::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'likeable_type' => Relation::getMorphAlias(Pet::class),
            'likeable_id' => Pet::factory(),
        ];
    }

    /**
     * Like a pet, creating one when none is given.
     */
    public function forPet(?Pet $pet = null): static
    {
        return $this->state(fn (array $attributes): array => [
            'likeable_type' => Relation::getMorphAlias(Pet::class),
            'likeable_id' => $pet?->getKey() ?? Pet::factory(),
        ]);
    }

    /**
     * Like a comment, creating one when none is given.
     */
    public function forComment(?Comment $comment = null): static
    {
        return $this->state(fn (array $attributes): array => [
            'likeable_type' => Relation::getMorphAlias(Comment::class),
            'likeable_id' => $comment?->getKey() ?? Comment::factory(),
        ]);
    }

    /**
     * Like a user profile, creating one when none is given.
     */
    public function forUser(?User $user = null): static
    {
        return $this->state(fn (array $attributes): array => [
            'likeable_type' => Relation::getMorphAlias(User::class),
            'likeable_id' => $user?->getKey() ?? User::factory(),
        ]);
    }
}
