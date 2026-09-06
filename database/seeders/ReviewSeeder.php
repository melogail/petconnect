<?php

namespace Database\Seeders;

use App\Models\Review;
use App\Models\User;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class ReviewSeeder extends Seeder
{
    /**
     * Share of users who are reviewed by others.
     */
    public const REVIEWED_SHARE = 0.35;

    /**
     * How many reviews a reviewed user receives.
     */
    public const MIN_PER_USER = 1;

    public const MAX_PER_USER = 5;

    /**
     * Review roughly a third of the members, the demo account included.
     *
     * Only users with no reviews are candidates, so a second run adds nothing.
     */
    public function run(): void
    {
        DB::transaction(function (): void {
            /** @var Collection<int, User> $users */
            $users = User::query()->select(['id'])->get();

            if ($users->count() < 2) {
                throw new RuntimeException('At least two users are needed to review each other; run UserSeeder first.');
            }

            foreach ($this->reviewedUsers($users->count()) as $reviewed) {
                $reviewers = $users
                    ->reject(fn (User $user): bool => $user->is($reviewed))
                    ->shuffle()
                    ->take(fake()->numberBetween(self::MIN_PER_USER, self::MAX_PER_USER));

                foreach ($reviewers as $reviewer) {
                    Review::factory()
                        ->for($reviewer)
                        ->forUser($reviewed)
                        ->create();
                }
            }
        });
    }

    /**
     * The users about to be reviewed: enough to bring the reviewed share up to
     * REVIEWED_SHARE, plus the demo account so its profile always shows a
     * rating. Topping the share up rather than reapplying it is what keeps a
     * second run a no-op.
     *
     * @return Collection<int, User>
     */
    protected function reviewedUsers(int $userCount): Collection
    {
        $target = (int) ceil($userCount * self::REVIEWED_SHARE);
        $missing = max(0, $target - User::query()->has('reviews')->count());

        /** @var Collection<int, User> $reviewed */
        $reviewed = User::query()
            ->doesntHave('reviews')
            ->inRandomOrder()
            ->limit($missing)
            ->get();

        $demo = User::query()
            ->where('email', UserSeeder::DEMO_EMAIL)
            ->doesntHave('reviews')
            ->first();

        if ($demo !== null && ! $reviewed->contains($demo)) {
            $reviewed->push($demo);
        }

        return $reviewed;
    }
}
