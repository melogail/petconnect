<?php

namespace Database\Factories;

use App\Enums\TicketType;
use App\Models\Contact;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Contact>
 */
class ContactFactory extends Factory
{
    /**
     * The name of the factory's corresponding model.
     *
     * @var class-string<Contact>
     */
    protected $model = Contact::class;

    /**
     * Define the model's default state: a message from a member, whose name
     * and address the Action copies off the account.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id'     => User::factory(),
            'name'        => fake()->name(),
            'email'       => fake()->safeEmail(),
            'ticket_type' => fake()->randomElement(TicketType::cases()),
            'subject'     => fake()->sentence(4),
            'message'     => fake()->paragraph(),
        ];
    }

    /**
     * A message from somebody without an account.
     */
    public function fromGuest(): static
    {
        return $this->state(fn (array $attributes): array => [
            'user_id' => null,
        ]);
    }
}
