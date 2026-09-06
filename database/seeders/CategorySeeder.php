<?php

namespace Database\Seeders;

use App\Models\Category;
use Database\Seeders\Concerns\ReadsSeedData;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use JsonException;

class CategorySeeder extends Seeder
{
    use ReadsSeedData;

    /**
     * Seed the taxonomy from database/data/categories.json.
     *
     * Keyed on the unique `slug`, so re-running the seeder refreshes the
     * translations instead of failing on the unique index.
     *
     * @throws JsonException
     */
    public function run(): void
    {
        DB::transaction(function (): void {
            /** @var list<array{slug: string, name: string, name_ar: string, description?: string|null, description_ar?: string|null}> $categories */
            $categories = $this->readSeedData('categories.json');

            foreach ($categories as $category) {
                Category::query()->updateOrCreate(
                    ['slug' => $category['slug']],
                    [
                        'name' => $category['name'],
                        'name_ar' => $category['name_ar'],
                        'description' => $category['description'] ?? null,
                        'description_ar' => $category['description_ar'] ?? null,
                    ],
                );
            }
        });
    }
}
