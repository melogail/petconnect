<?php

namespace Database\Factories;

use App\Models\Category;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * Random categories for tests. The real, translated taxonomy is seeded from
 * database/data/categories.json by CategorySeeder, so the Arabic columns are
 * left null here rather than filled with meaningless generated text.
 *
 * @extends Factory<Category>
 */
class CategoryFactory extends Factory
{
    /**
     * The name of the factory's corresponding model.
     *
     * @var class-string<Category>
     */
    protected $model = Category::class;

    /**
     * Define the model's default state.
     *
     * The name is drawn from Faker's unique pool so `slug`, which is unique,
     * never collides however many categories a test creates. Faker's pool only
     * knows about the names it has handed out, though, so the slug also carries
     * a random suffix to stay clear of the seven slugs CategorySeeder writes
     * from database/data/categories.json.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $name = fake()->unique()->words(2, true);

        return [
            'name' => Str::title($name),
            'name_ar' => null,
            'slug' => Str::slug($name).'-'.Str::lower(Str::random(6)),
            'description' => fake()->optional(0.8)->sentence(),
            'description_ar' => null,
        ];
    }
}
