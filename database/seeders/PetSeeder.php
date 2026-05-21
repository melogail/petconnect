<?php

namespace Database\Seeders;

use App\Enums\ListingType;
use App\Models\Breed;
use App\Models\Category;
use App\Models\Pet;
use App\Models\User;
use Illuminate\Database\Seeder;

class PetSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $users = User::all();
        $categories = Category::all();

        if ($users->isEmpty() || $categories->isEmpty()) {
            $this->command->warn('Please run UserSeeder and CategorySeeder first!');

            return;
        }

        // Create pets for each category
        foreach ($categories as $category) {
            $breeds = Breed::where('category_id', $category->id)->get();

            if ($breeds->isEmpty()) {
                continue;
            }

            // Create 8-12 pets per category
            $petCount = rand(8, 12);

            for ($i = 0; $i < $petCount; $i++) {
                $user = $users->random();
                $breed = $breeds->random();
                $listingType = fake()->randomElement([
                    ListingType::Adoption,
                    ListingType::Sale,
                    ListingType::Mating,
                ]);

                Pet::factory()->create([
                    'user_id' => $user->id,
                    'category_id' => $category->id,
                    'breed_id' => $breed->id,
                    'listing_type' => $listingType->value,
                    'price' => $listingType === ListingType::Sale ? fake()->randomFloat(2, 50, 2000) : null,
                ]);
            }
        }

        $this->command->info('Created '.Pet::count().' pets successfully!');
    }
}
