<?php

namespace Database\Factories;

use App\Models\Breed;
use App\Models\Category;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * Random breeds for tests. The real, translated breed list is seeded from
 * database/data/breeds.json by BreedSeeder.
 *
 * @extends Factory<Breed>
 */
class BreedFactory extends Factory
{
    /**
     * The name of the factory's corresponding model.
     *
     * @var class-string<Breed>
     */
    protected $model = Breed::class;

    /**
     * Define the model's default state.
     *
     * The name is drawn from Faker's unique pool so the composite
     * (category_id, slug) unique index never collides, even when many breeds
     * are created under a single category. That pool only knows the names it
     * has handed out, though, so the slug also carries a random suffix: a
     * factory breed created under a *seeded* category (Pet::factory()
     * ->for($seededCategory)) would otherwise be able to hit one of the slugs
     * BreedSeeder writes from database/data/breeds.json.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $name = fake()->unique()->words(2, true);

        return [
            'category_id' => Category::factory(),
            'name' => Str::title($name),
            'name_ar' => null,
            'slug' => Str::slug($name).'-'.Str::lower(Str::random(6)),
            'description' => fake()->optional(0.7)->sentence(),
            'description_ar' => null,
        ];
    }
}
