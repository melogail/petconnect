<?php

namespace Database\Factories;

use App\Enums\TicketCategory;
use App\Enums\TicketStatus;
use App\Models\Admin;
use App\Models\Ticket;
use App\Models\TicketMessage;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Ticket>
 */
class TicketFactory extends Factory
{
    /**
     * The name of the factory's corresponding model.
     *
     * @var class-string<Ticket>
     */
    protected $model = Ticket::class;

    /**
     * Define the model's default state: an open ticket from a member, whose
     * name and address the Action copies off the account, with the opening
     * message written by that member (see `configure()`).
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id'          => User::factory(),
            'name'             => fake()->name(),
            'email'            => fake()->safeEmail(),
            'category'         => fake()->randomElement(TicketCategory::cases()),
            'subject'          => fake()->sentence(4),
            'last_activity_at' => now(),
        ];
    }

    /**
     * Every ticket has at least its opening message: a ticket with an empty
     * thread cannot be produced by the application, so the factory does not
     * produce one either.
     */
    public function configure(): static
    {
        return $this->afterCreating(function (Ticket $ticket): void {
            if ($ticket->messages()->exists()) {
                return;
            }

            TicketMessage::factory()
                ->for($ticket)
                ->from($ticket->user)
                ->create(['created_at' => $ticket->created_at]);
        });
    }

    /**
     * A ticket whose member has since deleted their account: `user_id` nulled
     * by the FK, name and address still on the row.
     */
    public function fromDeletedAccount(): static
    {
        return $this->state(fn (array $attributes): array => [
            'user_id' => null,
        ]);
    }

    /**
     * A ticket the team has replied to and is now waiting on the sender.
     */
    public function answered(?string $reply = null): static
    {
        return $this->state(fn (array $attributes): array => [
            'status' => TicketStatus::Answered,
        ])->afterCreating(function (Ticket $ticket) use ($reply): void {
            TicketMessage::factory()
                ->for($ticket)
                ->from(Admin::factory()->create())
                ->create(['body' => $reply ?? fake()->paragraph()]);
        });
    }

    /**
     * A finished ticket.
     */
    public function closed(): static
    {
        return $this->state(fn (array $attributes): array => [
            'status'    => TicketStatus::Closed,
            'closed_at' => now(),
        ]);
    }
}
