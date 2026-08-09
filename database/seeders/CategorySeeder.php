<?php

namespace Database\Seeders;

use App\Models\Category;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\File;
use JsonException;
use RuntimeException;

class CategorySeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @throws JsonException
     */
    public function run(): void
    {
        $path = database_path('data/categories.json');

        if (! File::exists($path)) {
            throw new RuntimeException("Category seed data not found at [{$path}].");
        }

        /** @var list<array{slug: string, name: string, name_ar: string, description?: string|null, description_ar?: string|null}> $categories */
        $categories = json_decode(File::get($path), true, 512, JSON_THROW_ON_ERROR);

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
    }
}
