<?php

namespace Database\Seeders;

use App\Models\Comment;
use App\Models\Like;
use App\Models\Pet;
use App\Models\User;
use Illuminate\Database\Seeder;

class LikeSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $pets = Pet::all();
        $comments = Comment::all();
        $users = User::all();

        if ($pets->isEmpty() || $users->isEmpty()) {
            $this->command->warn('Please run PetSeeder and UserSeeder first!');

            return;
        }

        // Create likes for pets
        foreach ($pets as $pet) {
            $likeCount = rand(5, 20);
            $likedUsers = $users->random(min($likeCount, $users->count()));

            foreach ($likedUsers as $user) {
                try {
                    Like::create([
                        'user_id' => $user->id,
                        'likeable_type' => Pet::class,
                        'likeable_id' => $pet->id,
                    ]);
                } catch (\Exception $e) {
                    // Skip if duplicate (unique constraint)
                    continue;
                }
            }
        }

        // Create likes for comments
        foreach ($comments as $comment) {
            $likeCount = rand(0, 8);
            $likedUsers = $users->random(min($likeCount, $users->count()));

            foreach ($likedUsers as $user) {
                try {
                    Like::create([
                        'user_id' => $user->id,
                        'likeable_type' => Comment::class,
                        'likeable_id' => $comment->id,
                    ]);
                } catch (\Exception $e) {
                    // Skip if duplicate (unique constraint)
                    continue;
                }
            }
        }

        $this->command->info('Created '.Like::count().' likes successfully!');
    }
}
