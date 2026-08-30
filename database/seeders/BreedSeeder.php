<?php

namespace Database\Seeders;

use App\Models\Breed;
use App\Models\Category;
use Database\Seeders\Concerns\ReadsSeedData;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use JsonException;
use RuntimeException;

class BreedSeeder extends Seeder
{
    use ReadsSeedData;

    /**
     * Seed the breeds from database/data/breeds.json, which is keyed by
     * category slug.
     *
     * Keyed on the composite unique (category_id, slug), so re-running the
     * seeder refreshes the translations instead of failing.
     *
     * @throws JsonException
     */
    public function run(): void
    {
        DB::transaction(function (): void {
            /** @var array<string, list<array{slug: string, name: string, name_ar: string, description?: string|null, description_ar?: string|null}>> $breedsByCategory */
            $breedsByCategory = $this->readSeedData('breeds.json');

            foreach ($breedsByCategory as $categorySlug => $breeds) {
                $category = Category::query()->where('slug', $categorySlug)->first()
                    ?? throw new RuntimeException("Category [{$categorySlug}] is missing; run CategorySeeder first.");

                foreach ($breeds as $breed) {
                    Breed::query()->updateOrCreate(
                        [
                            'category_id' => $category->getKey(),
                            'slug' => $breed['slug'],
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
        });
    }
}
