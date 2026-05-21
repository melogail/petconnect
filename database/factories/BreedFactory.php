<?php

namespace Database\Factories;

use App\Models\Category;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Breed>
 */
class BreedFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $breeds = [
            'Golden Retriever', 'German Shepherd', 'Labrador', 'Bulldog', 'Beagle',
            'Persian Cat', 'Siamese Cat', 'Maine Coon', 'Bengal Cat', 'Ragdoll',
            'Parrot', 'Canary', 'Cockatiel', 'Budgie', 'Lovebird',
            'Goldfish', 'Betta', 'Guppy', 'Angelfish', 'Tetra',
            'Rabbit', 'Hamster', 'Guinea Pig', 'Chinchilla', 'Ferret',
            'Bearded Dragon', 'Leopard Gecko', 'Ball Python', 'Corn Snake', 'Iguana',
            'Chicken', 'Goat', 'Horse', 'Sheep', 'Pig',
        ];

        $name = fake()->unique()->randomElement($breeds);

        return [
            'category_id' => Category::factory(),
            'name' => $name,
            'slug' => Str::slug($name),
            'description' => fake()->optional(0.7)->sentence(),
        ];
    }
}
