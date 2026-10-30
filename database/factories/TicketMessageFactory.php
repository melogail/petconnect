<?php

namespace Database\Factories;

use App\Models\Admin;
use App\Models\Ticket;
use App\Models\TicketMessage;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<TicketMessage>
 */
class TicketMessageFactory extends Factory
{
    /**
     * The name of the factory's corresponding model.
     *
     * @var class-string<TicketMessage>
     */
    protected $model = TicketMessage::class;

    /**
     * Define the model's default state: a message with no author on record
     * (an account since deleted). Combine with `->for($ticket)` and `from()`
     * to name the member or admin who wrote it.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'ticket_id' => Ticket::factory(),
            'body'      => fake()->paragraph(),
        ];
    }

    /**
     * Written by the given member or admin; null leaves the author unknown.
     */
    public function from(User|Admin|null $author): static
    {
        return $this->state(fn (array $attributes): array => [
            'author_type' => $author?->getMorphClass(),
            'author_id'   => $author?->getKey(),
        ]);
    }
}
