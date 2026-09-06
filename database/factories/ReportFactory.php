<?php

namespace Database\Factories;

use App\Enums\ReportCategory;
use App\Enums\ReportReason;
use App\Enums\ReportStatus;
use App\Models\Comment;
use App\Models\Report;
use App\Models\Review;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Relations\Relation;

/**
 * Reports are unique per (user, reportable_type, reportable_id): a user may
 * report a given comment or review only once. Build them from distinct pairs.
 *
 * @extends Factory<Report>
 */
class ReportFactory extends Factory
{
    /**
     * The name of the factory's corresponding model.
     *
     * @var class-string<Report>
     */
    protected $model = Report::class;

    /**
     * Define the model's default state.
     *
     * The optional() metadata is wrapped in a closure so it is only built when
     * the value is kept: passthrough() takes an already-evaluated argument, and
     * Factory::expandAttributes() resolves the closure afterwards.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'reportable_type' => Relation::getMorphAlias(Review::class),
            'reportable_id' => Review::factory(),
            'category' => fake()->randomElement(ReportCategory::cases()),
            'reason' => fake()->randomElement(ReportReason::cases()),
            'description' => fake()->optional(0.7)->paragraph(),
            'status' => fake()->randomElement(ReportStatus::cases()),
            'metadata' => fake()->optional(0.5)->passthrough(
                fn (array $attributes): array => [
                    'ip_address' => fake()->ipv4(),
                    'user_agent' => fake()->userAgent(),
                ],
            ),
        ];
    }

    /**
     * File the report against a comment or a review, the two models on the
     * Reportable whitelist.
     */
    public function forReportable(Comment|Review $reportable): static
    {
        return $this->state(fn (array $attributes): array => [
            'reportable_type' => $reportable->getMorphClass(),
            'reportable_id' => $reportable->getKey(),
        ]);
    }

    /**
     * Leave the report awaiting a moderator decision.
     */
    public function pending(): static
    {
        return $this->state(fn (array $attributes): array => [
            'status' => ReportStatus::Pending,
        ]);
    }

    /**
     * Close the report, a moderator having acted on it.
     *
     * The companion of pending(): the default draws a random ReportStatus, so a
     * test that needs a report the moderation queue must *not* count cannot get
     * one from the definition.
     */
    public function resolved(): static
    {
        return $this->state(fn (array $attributes): array => [
            'status' => ReportStatus::Resolved,
        ]);
    }
}
