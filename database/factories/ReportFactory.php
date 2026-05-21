<?php

namespace Database\Factories;

use App\Enums\ReportCategory;
use App\Enums\ReportReason;
use App\Models\Review;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Report>
 */
class ReportFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'reportable_type' => Review::class,
            'reportable_id' => Review::factory(),
            'category' => fake()->randomElement(ReportCategory::cases())->value,
            'reason' => fake()->randomElement(ReportReason::cases())->value,
            'description' => fake()->optional(0.7)->paragraph(),
            'status' => fake()->randomElement(['pending', 'reviewed', 'resolved', 'dismissed']),
            'metadata' => fake()->optional(0.5)->passthrough([
                'ip_address' => fake()->ipv4(),
                'user_agent' => fake()->userAgent(),
            ]),
        ];
    }

    /**
     * Indicate that the report is for a specific item.
     */
    public function forReportable($type, $id): static
    {
        return $this->state(fn (array $attributes) => [
            'reportable_type' => $type,
            'reportable_id' => $id,
        ]);
    }

    /**
     * Indicate that the report is pending.
     */
    public function pending(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'pending',
        ]);
    }
}
