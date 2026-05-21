<?php

namespace Database\Seeders;

use App\Models\Comment;
use App\Models\Pet;
use App\Models\User;
use Illuminate\Database\Seeder;

class CommentSeeder extends Seeder
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

        // Create 2-5 comments for each pet
        foreach ($pets as $pet) {
            $commentCount = rand(2, 5);

            for ($i = 0; $i < $commentCount; $i++) {
                $comment = Comment::factory()->create([
                    'user_id' => $users->random()->id,
                    'commentable_type' => Pet::class,
                    'commentable_id' => $pet->id,
                    'parent_id' => null,
                ]);

                // 40% chance to have a reply
                if (rand(1, 100) <= 40) {
                    Comment::factory()->create([
                        'user_id' => $users->random()->id,
                        'commentable_type' => Pet::class,
                        'commentable_id' => $pet->id,
                        'parent_id' => $comment->id,
                    ]);
                }
            }
        }

        $this->command->info('Created '.Comment::count().' comments successfully!');
    }
}
