<?php

namespace Database\Seeders;

use App\Models\Pet;
use App\Models\Save;
use App\Models\User;
use Illuminate\Database\Seeder;

class SaveSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $pets = Pet::all();
        $users = User::all();

        if ($pets->isEmpty() || $users->isEmpty()) {
            $this->command->warn('Please run PetSeeder and UserSeeder first!');

            return;
        }

        // Each user saves 3-10 random pets
        foreach ($users as $user) {
            $saveCount = rand(3, 10);
            $savedPets = $pets->random(min($saveCount, $pets->count()));

            foreach ($savedPets as $pet) {
                try {
                    Save::create([
                        'user_id' => $user->id,
                        'saveable_type' => Pet::class,
                        'saveable_id' => $pet->id,
                    ]);
                } catch (\Exception $e) {
                    // Skip if duplicate (unique constraint)
                    continue;
                }
            }
        }

        $this->command->info('Created '.Save::count().' saves successfully!');
    }
}
