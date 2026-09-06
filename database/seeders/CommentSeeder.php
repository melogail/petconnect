<?php

namespace Database\Seeders;

use App\Models\Comment;
use App\Models\Pet;
use App\Models\User;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class CommentSeeder extends Seeder
{
    /**
     * How many top level comments each listing gets.
     */
    public const MIN_PER_PET = 2;

    public const MAX_PER_PET = 5;

    /**
     * Percentage of top level comments that draw a reply.
     */
    protected const REPLY_CHANCE = 40;

    /**
     * Comment on every listing that has no comments yet.
     *
     * Filtering on `doesntHave('comments')` and walking the result with
     * lazyById() keeps the seeder idempotent: a listing seeded on the first
     * run is no longer a candidate on the second.
     */
    public function run(): void
    {
        DB::transaction(function (): void {
            /** @var Collection<int, User> $authors */
            $authors = User::query()->select(['id'])->get();

            if ($authors->isEmpty()) {
                throw new RuntimeException('No users to write comments; run UserSeeder first.');
            }

            Pet::query()
                ->doesntHave('comments')
                ->lazyById()
                ->each(function (Pet $pet) use ($authors): void {
                    $count = fake()->numberBetween(self::MIN_PER_PET, self::MAX_PER_PET);

                    for ($created = 0; $created < $count; $created++) {
                        $comment = Comment::factory()
                            ->for($authors->random())
                            ->for($pet, 'commentable')
                            ->create();

                        if (fake()->boolean(self::REPLY_CHANCE)) {
                            Comment::factory()
                                ->for($authors->random())
                                ->reply($comment)
                                ->create();
                        }
                    }
                });
        });
    }
}
