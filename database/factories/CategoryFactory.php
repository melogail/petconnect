<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Category>
 */
class CategoryFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $categories = [
            'Dogs' => 'Man\'s best friend - loyal, loving, and full of energy',
            'Cats' => 'Independent and affectionate feline companions',
            'Birds' => 'Colorful and melodious feathered friends',
            'Fish' => 'Beautiful aquatic pets for your home',
            'Small Pets' => 'Rabbits, hamsters, guinea pigs and more',
            'Reptiles' => 'Exotic cold-blooded companions',
            'Farm Animals' => 'Chickens, goats, horses and other farm friends',
        ];

        $name = fake()->unique()->randomElement(array_keys($categories));

        return [
            'name' => $name,
            'slug' => Str::slug($name),
            'description' => $categories[$name],
        ];
    }
}
