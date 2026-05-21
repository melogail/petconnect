<?php

namespace Database\Seeders;

// use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        $this->call([
            // 1. Seed categories first (no dependencies)
            CategorySeeder::class,

            // 2. Seed breeds (depends on categories)
            BreedSeeder::class,

            // 3. Seed users and admins (no dependencies)
            UserSeeder::class,
            AdminSeeder::class,

            // 4. Seed pets (depends on users, categories, breeds)
            PetSeeder::class,

            // 5. Seed interactions (depend on pets and users)
            CommentSeeder::class,
            LikeSeeder::class,
            SaveSeeder::class,

            // 6. Seed reviews (depend on users)
            ReviewSeeder::class,

            // 7. Seed reports (depend on reviews)
            ReportSeeder::class,
        ]);
    }
}
