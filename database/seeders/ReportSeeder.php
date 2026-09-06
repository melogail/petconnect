<?php

namespace Database\Seeders;

use App\Models\Comment;
use App\Models\Report;
use App\Models\Review;
use App\Models\User;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class ReportSeeder extends Seeder
{
    /**
     * How many comments and reviews carry a report.
     */
    public const REPORTED_COMMENT_COUNT = 12;

    public const REPORTED_REVIEW_COUNT = 8;

    /**
     * Share of reports left awaiting a moderator decision.
     */
    protected const PENDING_CHANCE = 60;

    /**
     * File a handful of reports against comments and reviews.
     *
     * `reports` is unique on (user_id, reportable_type, reportable_id). Each
     * target is visited exactly once and draws exactly one reporter, so no
     * pair can repeat and no try/catch is needed. The reported totals are
     * topped up rather than reapplied, so a second run adds nothing.
     */
    public function run(): void
    {
        DB::transaction(function (): void {
            /** @var Collection<int, User> $users */
            $users = User::query()->select(['id'])->get();

            if ($users->count() < 2) {
                throw new RuntimeException('At least two users are needed to file reports; run UserSeeder first.');
            }

            $targets = [
                ...$this->unreported(Comment::class, self::REPORTED_COMMENT_COUNT)->all(),
                ...$this->unreported(Review::class, self::REPORTED_REVIEW_COUNT)->all(),
            ];

            foreach ($targets as $target) {
                $this->report($target, $users);
            }
        });
    }

    /**
     * Targets that carry no report yet, capped at what is still missing.
     *
     * @param  class-string<Comment|Review>  $model
     * @return Collection<int, Comment|Review>
     */
    protected function unreported(string $model, int $target): Collection
    {
        $missing = max(0, $target - $model::query()->has('reports')->count());

        /** @var Collection<int, Comment|Review> $targets */
        $targets = $model::query()
            ->doesntHave('reports')
            ->inRandomOrder()
            ->limit($missing)
            ->get();

        return $targets;
    }

    /**
     * File one report against a target, by someone other than its author.
     *
     * @param  Collection<int, User>  $users
     */
    protected function report(Comment|Review $target, Collection $users): void
    {
        $reporter = $users
            ->reject(fn (User $user): bool => $user->getKey() === $target->user_id)
            ->random();

        $factory = Report::factory()
            ->for($reporter)
            ->forReportable($target);

        if (fake()->boolean(self::PENDING_CHANCE)) {
            $factory = $factory->pending();
        }

        $factory->create();
    }
}
