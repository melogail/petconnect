<?php

namespace Database\Seeders;

use App\Models\Review;
use App\Models\User;
use Illuminate\Database\Seeder;

class ReviewSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $users = User::all();

        if ($users->count() < 2) {
            $this->command->warn('Need at least 2 users to create reviews!');

            return;
        }

        // Create reviews for random users (seller/adopter reviews)
        // 30-40% of users will have reviews
        $usersWithReviews = $users->random($users->count() * 0.35);

        foreach ($usersWithReviews as $reviewedUser) {
            $reviewCount = rand(1, 5);

            for ($i = 0; $i < $reviewCount; $i++) {
                // Get a different user as the reviewer
                $reviewer = $users->where('id', '!=', $reviewedUser->id)->random();

                Review::factory()->create([
                    'user_id' => $reviewer->id,
                    'reviewable_type' => User::class,
                    'reviewable_id' => $reviewedUser->id,
                ]);
            }
        }

        $this->command->info('Created '.Review::count().' reviews successfully!');
    }
}
