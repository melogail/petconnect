<?php

namespace Database\Factories;

use App\Models\Comment;
use App\Models\Pet;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Relations\Relation;

/**
 * @extends Factory<Comment>
 */
class CommentFactory extends Factory
{
    /**
     * The name of the factory's corresponding model.
     *
     * @var class-string<Comment>
     */
    protected $model = Comment::class;

    /**
     * Define the model's default state.
     *
     * The morph type is written as the alias registered in
     * AppServiceProvider::configureMorphMap(), never a class name.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'content' => fake()->sentence(fake()->numberBetween(5, 20)),
            'parent_id' => null,
            'commentable_type' => Relation::getMorphAlias(Pet::class),
            'commentable_id' => Pet::factory(),
        ];
    }

    /**
     * Comment on a listing, creating one when none is given.
     *
     * The same shape LikeFactory::forPet() and SaveFactory::forPet() have, and
     * for the same reason: the morph type is the registered alias, never a
     * class name, so a caller cannot write `Pet::class` into `commentable_type`
     * and match nothing.
     */
    public function forPet(?Pet $pet = null): static
    {
        return $this->state(fn (array $attributes): array => [
            'commentable_type' => Relation::getMorphAlias(Pet::class),
            'commentable_id' => $pet?->getKey() ?? Pet::factory(),
        ]);
    }

    /**
     * Make the comment a reply hanging off another comment, on the same
     * commentable as its parent.
     */
    public function reply(Comment $parent): static
    {
        return $this->state(fn (array $attributes): array => [
            'parent_id' => $parent->getKey(),
            'commentable_type' => $parent->commentable_type,
            'commentable_id' => $parent->commentable_id,
        ]);
    }
}
