<?php

namespace Database\Seeders;

use App\Models\Report;
use App\Models\Review;
use App\Models\User;
use Illuminate\Database\Seeder;

class ReportSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $reviews = Review::all();
        $users = User::all();

        if ($reviews->isEmpty() || $users->isEmpty()) {
            $this->command->warn('Please run ReviewSeeder and UserSeeder first!');

            return;
        }

        // Create reports for 10-15% of reviews
        $reportableReviews = $reviews->random(min(ceil($reviews->count() * 0.12), $reviews->count()));

        foreach ($reportableReviews as $review) {
            Report::factory()->create([
                'user_id' => $users->random()->id,
                'reportable_type' => Review::class,
                'reportable_id' => $review->id,
            ]);
        }

        $this->command->info('Created '.Report::count().' reports successfully!');
    }
}
