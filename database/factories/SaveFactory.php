<?php

namespace Database\Factories;

use App\Models\Pet;
use App\Models\Save;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Relations\Relation;

/**
 * Saves are unique per (user, saveable); build them from distinct pairs, or go
 * through Pet::addSave()/toggleSave(), which use firstOrCreate.
 *
 * @extends Factory<Save>
 */
class SaveFactory extends Factory
{
    /**
     * The name of the factory's corresponding model.
     *
     * @var class-string<Save>
     */
    protected $model = Save::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'saveable_type' => Relation::getMorphAlias(Pet::class),
            'saveable_id' => Pet::factory(),
        ];
    }

    /**
     * Bookmark a pet, creating one when none is given.
     */
    public function forPet(?Pet $pet = null): static
    {
        return $this->state(fn (array $attributes): array => [
            'saveable_type' => Relation::getMorphAlias(Pet::class),
            'saveable_id' => $pet?->getKey() ?? Pet::factory(),
        ]);
    }
}
