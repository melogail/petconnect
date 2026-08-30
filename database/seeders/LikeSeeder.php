<?php

namespace Database\Seeders;

use App\Models\Comment;
use App\Models\Pet;
use App\Models\User;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class LikeSeeder extends Seeder
{
    /**
     * How many likes a listing gets.
     */
    public const MIN_PER_PET = 2;

    public const MAX_PER_PET = 12;

    /**
     * How many likes a liked comment gets, and the share of comments that are
     * liked at all; the rest stay on zero.
     */
    public const MIN_PER_COMMENT = 1;

    public const MAX_PER_COMMENT = 6;

    public const LIKED_COMMENT_SHARE = 0.6;

    /**
     * Like listings and comments on behalf of distinct users.
     *
     * `likes` is unique on (user_id, likeable_id, likeable_type). Two things
     * keep that intact without a try/catch: the likers of one model are a
     * random subset of *distinct* users, and HasLikes::like() is a
     * firstOrCreate, so a repeat run cannot insert a duplicate.
     */
    public function run(): void
    {
        DB::transaction(function (): void {
            /** @var Collection<int, User> $users */
            $users = User::query()->select(['id'])->get();

            if ($users->isEmpty()) {
                throw new RuntimeException('No users to like anything; run UserSeeder first.');
            }

            $this->likePets($users);
            $this->likeComments($users);
        });
    }

    /**
     * Every listing gets likes, so a second run finds no candidates.
     *
     * @param  Collection<int, User>  $users
     */
    protected function likePets(Collection $users): void
    {
        Pet::query()
            ->doesntHave('likes')
            ->lazyById()
            ->each(function (Pet $pet) use ($users): void {
                $count = fake()->numberBetween(self::MIN_PER_PET, self::MAX_PER_PET);

                foreach ($users->shuffle()->take($count) as $liker) {
                    $pet->like($liker);
                }
            });
    }

    /**
     * Only a share of comments is liked, and the share is topped up rather
     * than reapplied, so a second run adds nothing and the rest of the
     * comments keep their empty like state.
     *
     * @param  Collection<int, User>  $users
     */
    protected function likeComments(Collection $users): void
    {
        $target = (int) ceil(Comment::query()->count() * self::LIKED_COMMENT_SHARE);
        $missing = max(0, $target - Comment::query()->has('likes')->count());

        Comment::query()
            ->doesntHave('likes')
            ->inRandomOrder()
            ->limit($missing)
            ->get()
            ->each(function (Comment $comment) use ($users): void {
                $count = fake()->numberBetween(self::MIN_PER_COMMENT, self::MAX_PER_COMMENT);

                foreach ($users->shuffle()->take($count) as $liker) {
                    $comment->like($liker);
                }
            });
    }
}
