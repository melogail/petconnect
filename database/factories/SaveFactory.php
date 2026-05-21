<?php

namespace Database\Factories;

use App\Models\Pet;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Save>
 */
class SaveFactory extends Factory
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
            'saveable_type' => Pet::class,
            'saveable_id' => Pet::factory(),
        ];
    }

    /**
     * Indicate that the save is for a specific pet.
     */
    public function forPet($petId): static
    {
        return $this->state(fn (array $attributes) => [
            'saveable_id' => $petId,
        ]);
    }
}
