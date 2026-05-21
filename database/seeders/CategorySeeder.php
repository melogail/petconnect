<?php

namespace Database\Seeders;

use App\Models\Category;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class CategorySeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $categories = [
            [
                'name' => 'Dogs',
                'description' => 'Man\'s best friend - loyal, loving, and full of energy',
            ],
            [
                'name' => 'Cats',
                'description' => 'Independent and affectionate feline companions',
            ],
            [
                'name' => 'Birds',
                'description' => 'Colorful and melodious feathered friends',
            ],
            [
                'name' => 'Fish',
                'description' => 'Beautiful aquatic pets for your home',
            ],
            [
                'name' => 'Small Pets',
                'description' => 'Rabbits, hamsters, guinea pigs and more',
            ],
            [
                'name' => 'Reptiles',
                'description' => 'Exotic cold-blooded companions',
            ],
            [
                'name' => 'Farm Animals',
                'description' => 'Chickens, goats, horses and other farm friends',
            ],
        ];

        foreach ($categories as $category) {
            Category::create([
                'name' => $category['name'],
                'slug' => Str::slug($category['name']),
                'description' => $category['description'],
            ]);
        }
    }
}
