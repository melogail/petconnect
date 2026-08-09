<?php

namespace Database\Seeders;

use App\Models\Breed;
use App\Models\Category;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use JsonException;
use RuntimeException;

class BreedSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @throws JsonException
     */
    public function run(): void
    {
        $path = database_path('data/breeds.json');

        if (! File::exists($path)) {
            throw new RuntimeException("Breed seed data not found at [{$path}].");
        }

        /** @var array<string, list<array{slug?: string, name: string, name_ar: string, description?: string|null, description_ar?: string|null}>> $breedsByCategory */
        $breedsByCategory = json_decode(File::get($path), true, 512, JSON_THROW_ON_ERROR);

        foreach ($breedsByCategory as $categorySlug => $breeds) {
            $category = Category::query()->where('slug', $categorySlug)->first();

            if ($category === null) {
                $this->command?->warn("Category [{$categorySlug}] not found. Skipping breeds.");

                continue;
            }

            foreach ($breeds as $breed) {
                $slug = $breed['slug'] ?? Str::slug($breed['name']);

                Breed::query()->updateOrCreate(
                    [
                        'category_id' => $category->id,
                        'slug' => $slug,
                    ],
                    [
                        'name' => $breed['name'],
                        'name_ar' => $breed['name_ar'],
                        'description' => $breed['description'] ?? null,
                        'description_ar' => $breed['description_ar'] ?? null,
                    ],
                );
            }
        }
    }
}
