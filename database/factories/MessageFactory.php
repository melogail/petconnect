<?php

namespace Database\Factories;

use App\Enums\MessageStatus;
use App\Enums\MessageType;
use App\Models\Conversation;
use App\Models\Message;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Message>
 */
class MessageFactory extends Factory
{
    /**
     * The name of the factory's corresponding model.
     *
     * @var class-string<Message>
     */
    protected $model = Message::class;

    /**
     * Define the model's default state.
     *
     * Both foreign keys are always populated; a message with no conversation
     * or no sender cannot be inserted. `status` is set to the same value the
     * column defaults to, so the model returned by create() matches the row and
     * `@property MessageStatus $status` stays true without a refresh(). Only
     * `pinned_by` and `pinned_at` are left unset; the pinned() state sets them.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'conversation_id' => Conversation::factory(),
            'sender_id' => User::factory(),
            'content' => fake()->sentence(fake()->numberBetween(3, 18)),
            'type' => MessageType::Text,
            'status' => MessageStatus::Sent,
        ];
    }

    /**
     * Send the message as the given user.
     */
    public function from(User $sender): static
    {
        return $this->state(fn (array $attributes): array => [
            'sender_id' => $sender->getKey(),
        ]);
    }

    /**
     * Pin the message, by the sender unless another user is given.
     */
    public function pinned(?User $pinnedBy = null): static
    {
        return $this->state(fn (array $attributes): array => [
            'pinned_by' => fn (array $resolved): mixed => $pinnedBy?->getKey() ?? $resolved['sender_id'],
            'pinned_at' => now(),
        ]);
    }
}
